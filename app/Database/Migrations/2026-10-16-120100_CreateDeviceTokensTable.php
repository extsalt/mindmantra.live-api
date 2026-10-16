<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration to create the device_tokens table for push notifications.
 */
class CreateDeviceTokensTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
            ],
            'token' => [
                'type'       => 'VARCHAR',
                'constraint' => '512',
            ],
            'platform' => [
                'type'       => 'VARCHAR',
                'constraint' => '30',
                'default'    => 'android',
            ],
            'device_id' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
            'device_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'last_used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('token');
        $this->forge->addKey('user_id');
        $this->forge->addKey('platform');
        $this->forge->addKey('is_active');
        $this->forge->addKey(['user_id', 'is_active']);
        $this->forge->createTable('device_tokens', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('device_tokens', true);
    }
}
