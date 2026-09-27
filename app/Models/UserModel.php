<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * UserModel represents the database table 'users'.
 */
class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'email',
        'name',
        'picture',
        'google_id',
        'password_hash',
        'role',
        'status',
        'created_at',
    ];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';

    // Validation
    protected $validationRules = [
        'email' => 'required|valid_email|is_unique[users.email,id,{id}]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Find a user by normalized email address.
     *
     * @param string $email
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $normalized = strtolower(trim($email));

        return $this->where('email', $normalized)->first();
    }
}
