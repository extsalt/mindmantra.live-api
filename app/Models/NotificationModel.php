<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * NotificationModel manages in-app notifications stored in the database.
 */
class NotificationModel extends Model
{
    protected $table            = 'notifications';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'title',
        'body',
        'type',
        'data',
        'image_url',
        'is_read',
        'read_at',
        'created_at',
        'updated_at',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Retrieve paginated notifications for a given user (including global broadcasts).
     *
     * @param int         $userId
     * @param int         $page
     * @param int         $perPage
     * @param string|null $status  'unread' | 'read' | null (all)
     * @return array<string, mixed>
     */
    public function getUserNotifications(int $userId, int $page = 1, int $perPage = 15, ?string $status = null): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        $builder = $this->builder();
        $builder->groupStart()
            ->where('user_id', $userId)
            ->orWhere('user_id IS NULL', null, false)
        ->groupEnd();

        if ($status === 'unread') {
            $builder->where('is_read', 0);
        } elseif ($status === 'read') {
            $builder->where('is_read', 1);
        }

        // Clone builder for total count
        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults();

        $rows = $builder->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        // Decode JSON data column
        foreach ($rows as &$row) {
            $row['id']      = (int) $row['id'];
            $row['user_id'] = $row['user_id'] !== null ? (int) $row['user_id'] : null;
            $row['is_read'] = (int) $row['is_read'];
            if (! empty($row['data']) && is_string($row['data'])) {
                $decoded = json_decode($row['data'], true);
                $row['data'] = $decoded !== null ? $decoded : $row['data'];
            } else {
                $row['data'] = null;
            }
        }
        unset($row);

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'data'         => $rows,
            'unread_count' => $this->getUnreadCount($userId),
            'pagination'   => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * Get count of unread notifications for a specific user.
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadCount(int $userId): int
    {
        return (int) $this->builder()
            ->groupStart()
                ->where('user_id', $userId)
                ->orWhere('user_id IS NULL', null, false)
            ->groupEnd()
            ->where('is_read', 0)
            ->countAllResults();
    }

    /**
     * Mark a specific notification as read for a user.
     *
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public function markAsRead(int $id, int $userId): bool
    {
        $now = date('Y-m-d H:i:s');

        return $this->builder()
            ->where('id', $id)
            ->groupStart()
                ->where('user_id', $userId)
                ->orWhere('user_id IS NULL', null, false)
            ->groupEnd()
            ->update([
                'is_read'    => 1,
                'read_at'    => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * Mark all unread notifications for a user as read.
     *
     * @param int $userId
     * @return int Number of updated rows
     */
    public function markAllAsRead(int $userId): int
    {
        $now = date('Y-m-d H:i:s');

        $this->builder()
            ->groupStart()
                ->where('user_id', $userId)
                ->orWhere('user_id IS NULL', null, false)
            ->groupEnd()
            ->where('is_read', 0)
            ->update([
                'is_read'    => 1,
                'read_at'    => $now,
                'updated_at' => $now,
            ]);

        return $this->db->affectedRows();
    }

    /**
     * Delete a notification belonging to the specified user.
     *
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public function deleteUserNotification(int $id, int $userId): bool
    {
        return (bool) $this->where('id', $id)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Create a new notification record.
     *
     * @param array<string, mixed> $payload
     * @return int|false
     */
    public function createNotification(array $payload): int|false
    {
        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload['data'] = json_encode($payload['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (! isset($payload['is_read'])) {
            $payload['is_read'] = 0;
        }

        return $this->insert($payload, true);
    }
}
