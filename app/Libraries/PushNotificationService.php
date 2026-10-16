<?php

namespace App\Libraries;

use App\Models\DeviceTokenModel;
use App\Models\NotificationModel;
use Config\PushNotification as PushConfig;

/**
 * PushNotificationService orchestrates in-app notifications and Firebase Cloud Messaging (FCM) push dispatching.
 */
class PushNotificationService
{
    private PushConfig $config;
    private NotificationModel $notificationModel;
    private DeviceTokenModel $deviceTokenModel;

    public function __construct(?PushConfig $config = null)
    {
        $this->config            = $config ?? config(PushConfig::class);
        $this->notificationModel = new NotificationModel();
        $this->deviceTokenModel  = new DeviceTokenModel();
    }

    /**
     * Send an in-app notification and dispatch push notification to all active devices of a user.
     *
     * @param int                  $userId
     * @param string               $title
     * @param string               $body
     * @param array<string, mixed> $data
     * @param string|null          $imageUrl
     * @param string               $type
     * @return array<string, mixed>
     */
    public function notifyUser(
        int $userId,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null,
        string $type = 'general'
    ): array {
        // 1. Store in-app notification in database
        $notificationId = $this->notificationModel->createNotification([
            'user_id'    => $userId,
            'title'      => $title,
            'body'       => $body,
            'type'       => $type,
            'data'       => $data,
            'image_url'  => $imageUrl,
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 2. Fetch active device tokens for user
        $tokens = $this->deviceTokenModel->getActiveTokensForUser($userId);

        $pushResult = [
            'dispatched'    => false,
            'devices_count' => count($tokens),
            'success_count' => 0,
            'failure_count' => 0,
        ];

        if (! empty($tokens) && $this->config->enabled) {
            $pushResult = $this->sendToTokens($tokens, $title, $body, $data, $imageUrl);
            $pushResult['dispatched'] = true;
            $pushResult['devices_count'] = count($tokens);
        }

        return [
            'notification_id' => $notificationId !== false ? (int) $notificationId : null,
            'user_id'         => $userId,
            'push'            => $pushResult,
        ];
    }

    /**
     * Broadcast a notification to all registered users and devices.
     *
     * @param string               $title
     * @param string               $body
     * @param array<string, mixed> $data
     * @param string|null          $imageUrl
     * @param string               $type
     * @return array<string, mixed>
     */
    public function broadcast(
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null,
        string $type = 'announcement'
    ): array {
        // 1. Insert global broadcast notification record (user_id = NULL)
        $notificationId = $this->notificationModel->createNotification([
            'user_id'    => null,
            'title'      => $title,
            'body'       => $body,
            'type'       => $type,
            'data'       => $data,
            'image_url'  => $imageUrl,
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 2. Fetch all active device tokens
        $tokens = $this->deviceTokenModel->getAllActiveTokens();
        $totalTokens = count($tokens);

        $totalSuccess = 0;
        $totalFailure = 0;

        if ($totalTokens > 0 && $this->config->enabled) {
            // Batch tokens into chunks up to configured batch limit (default 500)
            $batches = array_chunk($tokens, $this->config->batchLimit);
            foreach ($batches as $batch) {
                $batchResult = $this->sendToTokens($batch, $title, $body, $data, $imageUrl);
                $totalSuccess += (int) ($batchResult['success_count'] ?? 0);
                $totalFailure += (int) ($batchResult['failure_count'] ?? 0);
            }
        }

        return [
            'notification_id' => $notificationId !== false ? (int) $notificationId : null,
            'broadcast'       => true,
            'total_tokens'    => $totalTokens,
            'success_count'   => $totalSuccess,
            'failure_count'   => $totalFailure,
        ];
    }

    /**
     * Send push notification to a specific list of device tokens.
     *
     * @param list<string>         $tokens
     * @param string               $title
     * @param string               $body
     * @param array<string, mixed> $data
     * @param string|null          $imageUrl
     * @return array<string, mixed>
     */
    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null
    ): array {
        $tokens = array_values(array_filter(array_unique($tokens)));
        if (empty($tokens)) {
            return [
                'status'        => 'empty',
                'success_count' => 0,
                'failure_count' => 0,
            ];
        }

        // Check if FCM HTTP v1 or Legacy credentials are configured
        $serviceAccount = $this->getServiceAccountData();
        $serverKey      = $this->config->fcmServerKey;

        if ($serviceAccount !== null) {
            return $this->sendViaFcmV1($tokens, $title, $body, $data, $imageUrl, $serviceAccount);
        }

        if (empty($serverKey)) {
            // Simulation mode for local dev / unconfigured FCM
            log_message('info', sprintf(
                '[PushNotification] FCM server key not configured. Simulated push to %d device(s): "%s"',
                count($tokens),
                $title
            ));

            return [
                'status'        => 'simulated',
                'mode'          => 'development',
                'message'       => 'FCM credentials not configured; push was simulated.',
                'tokens_count'  => count($tokens),
                'success_count' => count($tokens),
                'failure_count' => 0,
            ];
        }


        // Prepare FCM Legacy HTTP API payload
        $notificationPayload = [
            'title'              => $title,
            'body'               => $body,
            'sound'              => $this->config->defaultSound,
            'android_channel_id' => $this->config->defaultChannelId,
            'click_action'       => $this->config->defaultClickAction,
        ];

        if (! empty($imageUrl)) {
            $notificationPayload['image'] = $imageUrl;
        }

        // Format data payload strings for cross-platform compatibility
        $stringData = [];
        foreach ($data as $k => $v) {
            $stringData[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }

        $postFields = [
            'notification'      => $notificationPayload,
            'data'              => $stringData,
            'priority'          => 'high',
            'content_available' => true,
        ];

        if (count($tokens) === 1) {
            $postFields['to'] = $tokens[0];
        } else {
            $postFields['registration_ids'] = $tokens;
        }

        return $this->executeFcmRequest($postFields, $tokens);
    }

    /**
     * Send push notification to a Firebase Topic (e.g. 'all', 'courses', 'updates').
     *
     * @param string               $topic
     * @param string               $title
     * @param string               $body
     * @param array<string, mixed> $data
     * @param string|null          $imageUrl
     * @return array<string, mixed>
     */
    public function sendToTopic(
        string $topic,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null
    ): array {
        $serverKey = $this->config->fcmServerKey;
        if (empty($serverKey)) {
            return [
                'status'  => 'simulated',
                'topic'   => $topic,
                'message' => 'FCM server key not configured; topic push was simulated.',
            ];
        }

        $cleanTopic = ltrim($topic, '/');
        if (! str_starts_with($cleanTopic, 'topics/')) {
            $cleanTopic = 'topics/' . $cleanTopic;
        }

        $notificationPayload = [
            'title'              => $title,
            'body'               => $body,
            'sound'              => $this->config->defaultSound,
            'android_channel_id' => $this->config->defaultChannelId,
        ];

        if (! empty($imageUrl)) {
            $notificationPayload['image'] = $imageUrl;
        }

        $stringData = [];
        foreach ($data as $k => $v) {
            $stringData[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }

        $postFields = [
            'to'           => '/' . $cleanTopic,
            'notification' => $notificationPayload,
            'data'         => $stringData,
            'priority'     => 'high',
        ];

        return $this->executeFcmRequest($postFields);
    }

    /**
     * Execute HTTP POST request to Firebase Cloud Messaging API.
     *
     * @param array<string, mixed> $postFields
     * @param list<string>         $targetTokens
     * @return array<string, mixed>
     */
    private function executeFcmRequest(array $postFields, array $targetTokens = []): array
    {
        $url = 'https://fcm.googleapis.com/fcm/send';

        $headers = [
            'Authorization: key=' . $this->config->fcmServerKey,
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            log_message('error', sprintf(
                '[PushNotification] FCM Request failed with code %d. Error: %s. Response: %s',
                $httpCode,
                $curlError,
                (string) $response
            ));

            return [
                'status'        => 'error',
                'http_code'     => $httpCode,
                'error'         => $curlError ?: 'FCM HTTP Error ' . $httpCode,
                'success_count' => 0,
                'failure_count' => count($targetTokens) ?: 1,
            ];
        }

        $body = json_decode((string) $response, true);
        $successCount = (int) ($body['success'] ?? 0);
        $failureCount = (int) ($body['failure'] ?? 0);

        // Check for invalid or stale tokens that should be deactivated
        if (! empty($body['results']) && is_array($body['results']) && ! empty($targetTokens)) {
            $invalidTokens = [];
            foreach ($body['results'] as $index => $result) {
                if (isset($result['error']) && in_array($result['error'], ['NotRegistered', 'InvalidRegistration'], true)) {
                    if (isset($targetTokens[$index])) {
                        $invalidTokens[] = $targetTokens[$index];
                    }
                }
            }

            if (! empty($invalidTokens)) {
                $this->deviceTokenModel->deactivateTokens($invalidTokens);
                log_message('info', sprintf(
                    '[PushNotification] Deactivated %d invalid device token(s).',
                    count($invalidTokens)
                ));
            }
        }

        return [
            'status'        => 'success',
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'multicast_id'  => $body['multicast_id'] ?? null,
        ];
    }

    /**
     * Retrieve and parse Firebase Service Account JSON if configured.
     *
     * @return array<string, mixed>|null
     */
    private function getServiceAccountData(): ?array
    {
        $raw = $this->config->fcmServiceAccountJson;
        if (empty($raw)) {
            return null;
        }

        if (is_file($raw)) {
            $raw = file_get_contents($raw);
        }

        if (empty($raw) || ! is_string($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return (is_array($decoded) && ! empty($decoded['private_key']) && ! empty($decoded['client_email']))
            ? $decoded
            : null;
    }

    /**
     * Dispatch notifications via FCM HTTP v1 API.
     *
     * @param list<string>         $tokens
     * @param string               $title
     * @param string               $body
     * @param array<string, mixed> $data
     * @param string|null          $imageUrl
     * @param array<string, mixed> $serviceAccount
     * @return array<string, mixed>
     */
    private function sendViaFcmV1(
        array $tokens,
        string $title,
        string $body,
        array $data,
        ?string $imageUrl,
        array $serviceAccount
    ): array {
        $projectId = $this->config->fcmProjectId ?: ($serviceAccount['project_id'] ?? null);
        if (empty($projectId)) {
            log_message('error', '[PushNotification] FCM v1 Project ID missing.');
            return ['status' => 'error', 'error' => 'FCM Project ID missing'];
        }

        $accessToken = $this->getOAuth2AccessToken($serviceAccount);
        if ($accessToken === null) {
            log_message('error', '[PushNotification] Failed to obtain OAuth2 access token for FCM v1.');
            return ['status' => 'error', 'error' => 'Failed to obtain Google OAuth2 access token'];
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $stringData = [];
        foreach ($data as $k => $v) {
            $stringData[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }

        $successCount = 0;
        $failureCount = 0;
        $invalidTokens = [];

        foreach ($tokens as $token) {
            $message = [
                'token'        => $token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data' => $stringData,
                'android' => [
                    'priority'     => 'high',
                    'notification' => [
                        'channel_id'   => $this->config->defaultChannelId,
                        'sound'        => $this->config->defaultSound,
                        'click_action' => $this->config->defaultClickAction,
                    ],
                ],
            ];

            if (! empty($imageUrl)) {
                $message['notification']['image'] = $imageUrl;
                $message['android']['notification']['image'] = $imageUrl;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json; UTF-8',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['message' => $message]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200) {
                $successCount++;
            } else {
                $failureCount++;
                $respData = json_decode((string) $resp, true);
                $errStatus = $respData['error']['status'] ?? '';
                if ($errStatus === 'NOT_FOUND' || $errStatus === 'UNREGISTERED') {
                    $invalidTokens[] = $token;
                }
            }
        }

        if (! empty($invalidTokens)) {
            $this->deviceTokenModel->deactivateTokens($invalidTokens);
        }

        return [
            'status'        => 'success',
            'protocol'      => 'fcm_v1',
            'success_count' => $successCount,
            'failure_count' => $failureCount,
        ];
    }

    /**
     * Exchange Google Service Account credentials for an OAuth2 Bearer access token.
     *
     * @param array<string, mixed> $sa
     * @return string|null
     */
    private function getOAuth2AccessToken(array $sa): ?string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ];

        $b64UrlHeader = AuthLibrary::base64UrlEncode(json_encode($header));
        $b64UrlClaims = AuthLibrary::base64UrlEncode(json_encode($claims));
        $signingInput = "{$b64UrlHeader}.{$b64UrlClaims}";

        $privateKey = openssl_pkey_get_private($sa['private_key']);
        if ($privateKey === false) {
            return null;
        }

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        $jwt = "{$signingInput}." . AuthLibrary::base64UrlEncode($signature);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($res)) {
            return null;
        }

        $data = json_decode((string) $res, true);

        return $data['access_token'] ?? null;
    }
}

