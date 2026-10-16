<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * PushNotification Configuration.
 */
class PushNotification extends BaseConfig
{
    /**
     * Master switch to enable/disable push notification dispatching.
     */
    public bool $enabled = true;

    /**
     * Firebase Cloud Messaging (FCM) Server Key (Legacy HTTP API protocol).
     */
    public ?string $fcmServerKey = null;

    /**
     * Firebase Project ID (for FCM HTTP v1 protocol).
     */
    public ?string $fcmProjectId = null;

    /**
     * Path or raw JSON string for Firebase Service Account Key (FCM v1).
     */
    public ?string $fcmServiceAccountJson = null;

    /**
     * Default notification sound.
     */
    public string $defaultSound = 'default';

    /**
     * Default notification icon / channel id for Android.
     */
    public string $defaultChannelId = 'mindmantra_notifications';

    /**
     * Default click action / intent for mobile clients.
     */
    public string $defaultClickAction = 'FLUTTER_NOTIFICATION_CLICK';

    /**
     * Maximum number of device tokens per multicast batch.
     */
    public int $batchLimit = 500;

    public function __construct()
    {
        parent::__construct();

        $envEnabled = env('PUSH_ENABLED');
        if ($envEnabled !== null) {
            $this->enabled = filter_var($envEnabled, FILTER_VALIDATE_BOOLEAN);
        }

        $this->fcmServerKey          = env('FCM_SERVER_KEY') ?: null;
        $this->fcmProjectId          = env('FCM_PROJECT_ID') ?: null;
        $this->fcmServiceAccountJson = env('FCM_SERVICE_ACCOUNT_JSON') ?: null;
        $this->defaultChannelId      = env('FCM_CHANNEL_ID') ?: $this->defaultChannelId;
    }
}
