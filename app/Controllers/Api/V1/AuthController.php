<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Libraries\AuthLibrary;
use App\Models\UserModel;
use CodeIgniter\Cookie\Cookie;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * AuthController manages authentication endpoints for the API.
 */
class AuthController extends BaseController
{
    /**
     * Authenticate user with email and password.
     *
     * Expected payload:
     *   - email: string (required)
     *   - password: string (required)
     */
    public function login(): ResponseInterface
    {
        $input = $this->getRequestInput();
        if (! is_array($input)) {
            $input = [];
        }

        // 1. Validation rules
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required',
        ];

        $messages = [
            'email' => [
                'required'    => 'Email address is required.',
                'valid_email' => 'Please provide a valid email address.',
            ],
            'password' => [
                'required' => 'Password is required.',
            ],
        ];

        if (! $this->validateData($input, $rules, $messages)) {
            return $this->respond([
                'status'   => 400,
                'success'  => false,
                'message'  => 'Validation failed',
                'errors'   => $this->validator->getErrors(),
            ], 400);
        }

        $email    = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        // 2. Fetch user record
        $userModel = new UserModel();
        $user      = $userModel->findByEmail($email);

        if ($user === null || empty($user['password_hash'])) {
            return $this->respond([
                'status'   => 401,
                'success'  => false,
                'message'  => 'Invalid email or password.',
            ], 401);
        }

        // 3. Verify password
        if (! AuthLibrary::verifyPassword($password, $user['password_hash'])) {
            return $this->respond([
                'status'   => 401,
                'success'  => false,
                'message'  => 'Invalid email or password.',
            ], 401);
        }

        // 4. Verify account status (active = 1)
        if (isset($user['status']) && (int) $user['status'] !== 1) {
            return $this->respond([
                'status'   => 403,
                'success'  => false,
                'message'  => 'Account is inactive or disabled. Please contact support.',
            ], 403);
        }

        // 5. Build user profile payload
        $role = $user['role'] ?? 'user';
        if (strtolower($user['email']) === 'ganeshk.work@gmail.com') {
            $role = 'admin';
        }

        $userProfile = [
            'id'       => (string) $user['id'],
            'email'    => $user['email'],
            'name'     => ! empty($user['name']) ? $user['name'] : explode('@', $user['email'])[0],
            'picture'  => $user['picture'] ?? '',
            'googleId' => $user['google_id'] ?? '',
            'role'     => $role,
        ];

        // 6. Generate cryptographically signed session token
        $sessionToken = AuthLibrary::createSessionToken($userProfile);

        // 7. Attach HTTP-only session cookie (30 days validity)
        $isSecure = (ENVIRONMENT === 'production');
        $cookie   = new Cookie(
            'mm_user_session',
            $sessionToken,
            [
                'expires'  => time() + (30 * 24 * 60 * 60),
                'path'     => '/',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => Cookie::SAMESITE_LAX,
            ]
        );

        return $this->respond([
            'status'   => 200,
            'success'  => true,
            'message'  => 'Login successful',
            'data'     => [
                'user'  => $userProfile,
                'token' => $sessionToken,
            ],
        ], 200)->setCookie($cookie);
    }

    /**
     * Register a new user with email and password.
     *
     * Expected payload:
     *   - email: string (required)
     *   - password: string (required)
     */
    public function signup(): ResponseInterface
    {
        $input = $this->getRequestInput();
        if (! is_array($input)) {
            $input = [];
        }

        // 1. Validation rules
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[8]',
        ];

        $messages = [
            'email' => [
                'required'    => 'Email address is required.',
                'valid_email' => 'Please provide a valid email address.',
            ],
            'password' => [
                'required'   => 'Password is required.',
                'min_length' => 'Password must be at least 8 characters in length.',
            ],
        ];

        if (! $this->validateData($input, $rules, $messages)) {
            return $this->respond([
                'status'   => 400,
                'success'  => false,
                'message'  => 'Validation failed',
                'errors'   => $this->validator->getErrors(),
            ], 400);
        }

        $email    = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        // 2. Check if user already exists
        $userModel = new UserModel();
        if ($userModel->findByEmail($email) !== null) {
            return $this->respond([
                'status'   => 409,
                'success'  => false,
                'message'  => 'An account with this email address already exists.',
            ], 409);
        }

        // 3. Hash password
        $passwordHash = AuthLibrary::hashPassword($password);
        $displayName  = explode('@', $email)[0];
        $role         = (strtolower($email) === 'ganeshk.work@gmail.com') ? 'admin' : 'user';

        // 4. Create user record
        $userId = $userModel->insert([
            'email'         => $email,
            'name'          => $displayName,
            'picture'       => '',
            'google_id'     => null,
            'password_hash' => $passwordHash,
            'role'          => $role,
            'status'        => 1,
        ], true);

        if (! $userId) {
            return $this->respond([
                'status'   => 500,
                'success'  => false,
                'message'  => 'Failed to create user account.',
            ], 500);
        }

        // 5. Build user profile payload
        $userProfile = [
            'id'       => (string) $userId,
            'email'    => $email,
            'name'     => $displayName,
            'picture'  => '',
            'googleId' => '',
            'role'     => $role,
        ];

        // 6. Generate cryptographically signed session token
        $sessionToken = AuthLibrary::createSessionToken($userProfile);

        // 7. Attach HTTP-only session cookie (30 days validity)
        $isSecure = (ENVIRONMENT === 'production');
        $cookie   = new Cookie(
            'mm_user_session',
            $sessionToken,
            [
                'expires'  => time() + (30 * 24 * 60 * 60),
                'path'     => '/',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => Cookie::SAMESITE_LAX,
            ]
        );

        return $this->respond([
            'status'   => 201,
            'success'  => true,
            'message'  => 'User registered successfully.',
            'data'     => [
                'user'  => $userProfile,
                'token' => $sessionToken,
            ],
        ], 201)->setCookie($cookie);
    }
}
