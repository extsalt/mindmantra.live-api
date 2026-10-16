<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Libraries\PushNotificationService;
use App\Models\DeviceTokenModel;
use App\Models\NotificationModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * NotificationController manages in-app notifications and push notifications.
 */
class NotificationController extends BaseController
{
    private NotificationModel $notificationModel;
    private DeviceTokenModel $deviceTokenModel;
    private PushNotificationService $pushService;

    public function __construct()
    {
        $this->notificationModel = new NotificationModel();
        $this->deviceTokenModel  = new DeviceTokenModel();
        $this->pushService       = new PushNotificationService();
    }

    /**
     * Get paginated notifications for the authenticated user.
     *
     * GET /api/v1/notifications
     * Query Parameters:
     *   - page: int (default: 1)
     *   - per_page: int (default: 15, max: 100)
     *   - status: string ('unread' | 'read' | 'all')
     */
    public function index(): ResponseInterface
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->sendError('Unauthorized. Please log in to view your notifications.', 401);
        }

        $userId  = (int) $user['id'];
        $page    = max(1, (int) ($this->request->getGet('page') ?: 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?: 15)));
        $status  = $this->request->getGet('status');

        if (! in_array($status, ['unread', 'read'], true)) {
            $status = null;
        }

        $result = $this->notificationModel->getUserNotifications($userId, $page, $perPage, $status);

        return $this->respond([
            'status'       => 200,
            'success'      => true,
            'unread_count' => $result['unread_count'],
            'data'         => $result['data'],
            'pagination'   => $result['pagination'],
        ], 200);
    }

    /**
     * Get unread notification badge count.
     *
     * GET /api/v1/notifications/unread-count
     */
    public function unreadCount(): ResponseInterface
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->sendError('Unauthorized.', 401);
        }

        $userId = (int) $user['id'];
        $count  = $this->notificationModel->getUnreadCount($userId);

        return $this->respond([
            'status'       => 200,
            'success'      => true,
            'unread_count' => $count,
        ], 200);
    }

    /**
     * Get single notification details.
     *
     * GET /api/v1/notifications/(:id)
     */
    public function show(int $id): ResponseInterface
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->sendError('Unauthorized.', 401);
        }

        $userId = (int) $user['id'];
        $notification = $this->notificationModel->find($id);

        if ($notification === null) {
            return $this->sendError('Notification not found.', 404);
        }

        // Check ownership (must belong to user or be a global notification)
        if ($notification['user_id'] !== null && (int) $notification['user_id'] !== $userId) {
            return $this->sendError('Access denied.', 403);
        }

        if (! empty($notification['data']) && is_string($notification['data'])) {
            $decoded = json_decode($notification['data'], true);
            $notification['data'] = $decoded !== null ? $decoded : $notification['data'];
        }

        $notification['id']      = (int) $notification['id'];
        $notification['user_id'] = $notification['user_id'] !== null ? (int) $notification['user_id'] : null;
        $notification['is_read'] = (int) $notification['is_read'];

        return $this->respond([
            'status'  => 200,
            'success' => true,
            'data'    => $notification,
        ], 200);
    }

    /**
     * Mark a specific notification as read.
     *
     * POST|PUT /api/v1/notifications/(:id)/read
     */
    public function markAsRead(int $id): ResponseInterface
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->sendError('Unauthorized.', 401);
        }

        $userId  = (int) $user['id'];
        $updated = $this->notificationModel->markAsRead($id, $userId);

        if (! $updated) {
            return $this->sendError('Notification not found or already marked as read.', 404);
        }

        return $this->respond([
            'status'       => 200,
            'success'      => true,
            'message'      => 'Notification marked as read.',
            'unread_count' => $this->notificationModel->getUnreadCount($userId),
        ], 200);
    }

    /**
     * Mark all notifications for current user as read.
     *
     * POST|PUT /api/v1/notifications/read-all
     */
    public function markAllAsRead(): ResponseInterface
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->sendError('Unauthorized.', 401);
        }

        $userId  = (int) $user['id'];
        $affected = $this->notificationModel->markAllAsRead($userId);

        return $this->respond([
            'status'        => 200,
            'success'       => true,
            'message'       => 'All notifications marked as read.',
            'marked_count'  => $affected,
            'unread_count'  => 0,
        ], 200);
    }

    /**
     * Delete a notification.
     *
     * DELETE /api/v1/notifications/(:id)
     */
    public function delete(int $id): ResponseInterface
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->sendError('Unauthorized.', 401);
        }

        $userId  = (int) $user['id'];
        $deleted = $this->notificationModel->deleteUserNotification($id, $userId);

        if (! $deleted) {
            return $this->sendError('Notification not found or cannot be deleted.', 404);
        }

        return $this->respond([
            'status'       => 200,
            'success'      => true,
            'message'      => 'Notification deleted successfully.',
            'unread_count' => $this->notificationModel->getUnreadCount($userId),
        ], 200);
    }

    /**
     * Register or update a device push token.
     * Supports both authenticated users and anonymous guest app launches.
     *
     * POST /api/v1/notifications/devices/register
     * Expected Payload:
     *   - token: string (required) - FCM device token
     *   - platform: string (optional) - 'android' | 'ios' | 'web' (default: 'android')
     *   - device_id: string (optional) - Hardware/client UUID
     *   - device_name: string (optional) - e.g. "Samsung Galaxy S23"
     */
    public function registerDevice(): ResponseInterface
    {
        $input = $this->getRequestInput();
        if (! is_array($input)) {
            $input = [];
        }

        $rules = [
            'token'    => 'required|min_length[10]',
            'platform' => 'permit_empty|in_list[android,ios,web]',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->respond([
                'status'  => 400,
                'success' => false,
                'error'   => 'Validation failed',
                'errors'  => $this->validator->getErrors(),
            ], 400);
        }

        $token      = trim((string) $input['token']);
        $platform   = ! empty($input['platform']) ? strtolower(trim((string) $input['platform'])) : 'android';
        $deviceId   = ! empty($input['device_id']) ? trim((string) $input['device_id']) : null;
        $deviceName = ! empty($input['device_name']) ? trim((string) $input['device_name']) : null;

        $user   = $this->getAuthUser();
        $userId = $user !== null ? (int) $user['id'] : null;

        $result = $this->deviceTokenModel->registerToken(
            $token,
            $platform,
            $userId,
            $deviceId,
            $deviceName
        );

        return $this->respond([
            'status'  => 200,
            'success' => true,
            'message' => 'Device token successfully registered.',
            'data'    => $result,
        ], 200);
    }

    /**
     * Unregister / deactivate a device token on logout or app removal.
     *
     * POST /api/v1/notifications/devices/unregister
     * Expected Payload:
     *   - token: string (required)
     */
    public function unregisterDevice(): ResponseInterface
    {
        $input = $this->getRequestInput();
        if (! is_array($input)) {
            $input = [];
        }

        $rules = [
            'token' => 'required',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->respond([
                'status'  => 400,
                'success' => false,
                'error'   => 'Device token is required.',
            ], 400);
        }

        $token  = trim((string) $input['token']);
        $user   = $this->getAuthUser();
        $userId = $user !== null ? (int) $user['id'] : null;

        $this->deviceTokenModel->unregisterToken($token, $userId);

        return $this->respond([
            'status'  => 200,
            'success' => true,
            'message' => 'Device token unregistered successfully.',
        ], 200);
    }

    /**
     * Send push notification & in-app alert to a specific user.
     * Requires authentication.
     *
     * POST /api/v1/notifications/send
     * Expected Payload:
     *   - user_id: int (required)
     *   - title: string (required)
     *   - body: string (required)
     *   - type: string (optional, default: 'general')
     *   - data: array (optional)
     *   - image_url: string (optional)
     */
    public function send(): ResponseInterface
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->sendError('Unauthorized.', 401);
        }

        // Restrict notification dispatching to admin or self
        $senderRole = $user['role'] ?? 'user';
        $senderId   = (int) $user['id'];

        $input = $this->getRequestInput();
        if (! is_array($input)) {
            $input = [];
        }

        $rules = [
            'user_id' => 'required|is_natural_no_zero',
            'title'   => 'required|max_length[255]',
            'body'    => 'required',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->respond([
                'status'  => 400,
                'success' => false,
                'error'   => 'Validation failed',
                'errors'  => $this->validator->getErrors(),
            ], 400);
        }

        $targetUserId = (int) $input['user_id'];

        if ($senderRole !== 'admin' && $targetUserId !== $senderId) {
            return $this->sendError('Permission denied. Admin privileges required to dispatch notifications to other users.', 403);
        }

        $title    = trim((string) $input['title']);
        $body     = trim((string) $input['body']);
        $type     = ! empty($input['type']) ? trim((string) $input['type']) : 'general';
        $data     = isset($input['data']) && is_array($input['data']) ? $input['data'] : [];
        $imageUrl = ! empty($input['image_url']) ? trim((string) $input['image_url']) : null;

        $result = $this->pushService->notifyUser($targetUserId, $title, $body, $data, $imageUrl, $type);

        return $this->respond([
            'status'  => 200,
            'success' => true,
            'message' => 'Notification dispatched successfully.',
            'data'    => $result,
        ], 200);
    }

    /**
     * Broadcast notification to all active users and registered devices.
     * Requires admin role.
     *
     * POST /api/v1/notifications/broadcast
     * Expected Payload:
     *   - title: string (required)
     *   - body: string (required)
     *   - type: string (optional, default: 'announcement')
     *   - data: array (optional)
     *   - image_url: string (optional)
     */
    public function broadcast(): ResponseInterface
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->sendError('Unauthorized.', 401);
        }

        if (($user['role'] ?? 'user') !== 'admin') {
            return $this->sendError('Permission denied. Admin privileges required for broadcast.', 403);
        }

        $input = $this->getRequestInput();
        if (! is_array($input)) {
            $input = [];
        }

        $rules = [
            'title' => 'required|max_length[255]',
            'body'  => 'required',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->respond([
                'status'  => 400,
                'success' => false,
                'error'   => 'Validation failed',
                'errors'  => $this->validator->getErrors(),
            ], 400);
        }

        $title    = trim((string) $input['title']);
        $body     = trim((string) $input['body']);
        $type     = ! empty($input['type']) ? trim((string) $input['type']) : 'announcement';
        $data     = isset($input['data']) && is_array($input['data']) ? $input['data'] : [];
        $imageUrl = ! empty($input['image_url']) ? trim((string) $input['image_url']) : null;

        $result = $this->pushService->broadcast($title, $body, $data, $imageUrl, $type);

        return $this->respond([
            'status'  => 200,
            'success' => true,
            'message' => 'Broadcast notification dispatched successfully.',
            'data'    => $result,
        ], 200);
    }

    /**
     * Test push notification delivery directly to a single FCM device token.
     *
     * POST /api/v1/notifications/test-push
     * Expected Payload:
     *   - token: string (required)
     *   - title: string (optional)
     *   - body: string (optional)
     *   - data: array (optional)
     */
    public function testPush(): ResponseInterface
    {
        $input = $this->getRequestInput();
        if (! is_array($input)) {
            $input = [];
        }

        $rules = [
            'token' => 'required',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->respond([
                'status'  => 400,
                'success' => false,
                'error'   => 'Device token is required.',
            ], 400);
        }

        $token = trim((string) $input['token']);
        $title = ! empty($input['title']) ? trim((string) $input['title']) : 'MindMantra Test Notification';
        $body  = ! empty($input['body']) ? trim((string) $input['body']) : 'This is a test notification from MindMantra API.';
        $data  = isset($input['data']) && is_array($input['data']) ? $input['data'] : ['test' => true, 'timestamp' => time()];

        $result = $this->pushService->sendToTokens([$token], $title, $body, $data);

        return $this->respond([
            'status'  => 200,
            'success' => true,
            'message' => 'Test push dispatched.',
            'data'    => $result,
        ], 200);
    }
}
