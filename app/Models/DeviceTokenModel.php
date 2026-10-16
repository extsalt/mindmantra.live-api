<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * DeviceTokenModel manages FCM device tokens for mobile and web clients.
 */
class DeviceTokenModel extends Model
{
    protected $table            = 'device_tokens';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'token',
        'platform',
        'device_id',
        'device_name',
        'is_active',
        'last_used_at',
        'created_at',
        'updated_at',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Register or update an FCM device token.
     *
     * @param string      $token
     * @param string      $platform
     * @param int|null    $userId
     * @param string|null $deviceId
     * @param string|null $deviceName
     * @return array<string, mixed>
     */
    public function registerToken(
        string $token,
        string $platform = 'android',
        ?int $userId = null,
        ?string $deviceId = null,
        ?string $deviceName = null
    ): array {
        $now = date('Y-m-d H:i:s');
        $platform = strtolower(trim($platform));
        if (! in_array($platform, ['android', 'ios', 'web'], true)) {
            $platform = 'android';
        }

        $existing = $this->where('token', $token)->first();

        if ($existing !== null) {
            $updateData = [
                'platform'     => $platform,
                'is_active'    => 1,
                'last_used_at' => $now,
                'updated_at'   => $now,
            ];

            if ($userId !== null) {
                $updateData['user_id'] = $userId;
            }
            if (! empty($deviceId)) {
                $updateData['device_id'] = $deviceId;
            }
            if (! empty($deviceName)) {
                $updateData['device_name'] = $deviceName;
            }

            $this->update($existing['id'], $updateData);

            return [
                'id'       => (int) $existing['id'],
                'token'    => $token,
                'platform' => $platform,
                'action'   => 'updated',
            ];
        }

        $insertData = [
            'token'        => $token,
            'platform'     => $platform,
            'user_id'      => $userId,
            'device_id'    => $deviceId,
            'device_name'  => $deviceName,
            'is_active'    => 1,
            'last_used_at' => $now,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];

        $insertedId = $this->insert($insertData, true);

        return [
            'id'       => (int) $insertedId,
            'token'    => $token,
            'platform' => $platform,
            'action'   => 'created',
        ];
    }

    /**
     * Unregister / deactivate a device token.
     *
     * @param string   $token
     * @param int|null $userId
     * @return bool
     */
    public function unregisterToken(string $token, ?int $userId = null): bool
    {
        $builder = $this->builder()->where('token', $token);
        if ($userId !== null) {
            $builder->where('user_id', $userId);
        }

        return $builder->update([
            'is_active'  => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Retrieve list of active push tokens for a given user.
     *
     * @param int $userId
     * @return list<string>
     */
    public function getActiveTokensForUser(int $userId): array
    {
        $results = $this->select('token')
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->findAll();

        return array_column($results, 'token');
    }

    /**
     * Retrieve all active push tokens (for broadcast push notifications).
     *
     * @param string|null $platform
     * @param int         $limit
     * @return list<string>
     */
    public function getAllActiveTokens(?string $platform = null, int $limit = 5000): array
    {
        $builder = $this->select('token')->where('is_active', 1);

        if (! empty($platform)) {
            $builder->where('platform', strtolower($platform));
        }

        $results = $builder->limit($limit)->findAll();

        return array_column($results, 'token');
    }

    /**
     * Deactivate multiple invalid/expired tokens (e.g., reported by FCM as unregistered).
     *
     * @param list<string> $tokens
     * @return int Number of deactivated records
     */
    public function deactivateTokens(array $tokens): int
    {
        if (empty($tokens)) {
            return 0;
        }

        $this->builder()
            ->whereIn('token', $tokens)
            ->update([
                'is_active'  => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return $this->db->affectedRows();
    }
}
