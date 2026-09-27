<?php

namespace App\Libraries;

/**
 * AuthLibrary handles password hashing, password verification, and
 * cryptographically signed session token generation and verification.
 *
 * Implements interoperability with MindMantra's existing scrypt-based
 * authentication scheme and HMAC-SHA256 session tokens.
 */
class AuthLibrary
{
    /**
     * Default development session secret fallback.
     */
    private const DEFAULT_DEV_SESSION_SECRET = 'mindmantra-dev-session-secret-change-in-env';

    /**
     * Retrieve the cryptographic session secret.
     */
    public static function getSessionSecret(): string
    {
        $secret = env('SESSION_SECRET') ?: getenv('SESSION_SECRET');
        if (! empty($secret) && is_string($secret)) {
            return $secret;
        }

        return self::DEFAULT_DEV_SESSION_SECRET;
    }

    /**
     * Hash password using scrypt (Node.js crypto.scryptSync compatible).
     * Format: {32-char hex salt}:{128-char hex scrypt hash}
     */
    public static function hashPassword(string $password): string
    {
        $saltHex = bin2hex(random_bytes(16)); // 16 bytes = 32 hex chars

        if (function_exists('sodium_crypto_pwhash_scryptsalsa208sha256')) {
            $hashRaw = sodium_crypto_pwhash_scryptsalsa208sha256(
                64,
                $password,
                $saltHex,
                524288,
                16777216
            );
            $hashHex = bin2hex($hashRaw);

            return "{$saltHex}:{$hashHex}";
        }

        // Fallback to standard bcrypt if sodium scrypt is unavailable
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verify a plain password against the stored password hash.
     * Supports:
     *   1) Scrypt {salt}:{hash} format (161 characters, 32 hex salt + ':' + 128 hex hash)
     *   2) Standard PHP password_hash format ($2y$, $argon2id$, etc.)
     */
    public static function verifyPassword(string $password, ?string $storedHash): bool
    {
        if (empty($storedHash)) {
            return false;
        }

        // Check for scrypt {salt}:{hash} format
        if (str_contains($storedHash, ':')) {
            $parts = explode(':', $storedHash, 2);
            if (count($parts) !== 2) {
                return false;
            }

            [$saltHex, $expectedHashHex] = $parts;

            // Validate hex characters and lengths (salt: 32 hex chars, hash: 128 hex chars)
            if (strlen($saltHex) !== 32 || strlen($expectedHashHex) !== 128) {
                return false;
            }

            if (! ctype_xdigit($saltHex) || ! ctype_xdigit($expectedHashHex)) {
                return false;
            }

            if (function_exists('sodium_crypto_pwhash_scryptsalsa208sha256')) {
                $actualHashRaw = sodium_crypto_pwhash_scryptsalsa208sha256(
                    64,
                    $password,
                    $saltHex,
                    524288,
                    16777216
                );
                $actualHashHex = bin2hex($actualHashRaw);

                return hash_equals($expectedHashHex, $actualHashHex);
            }
        }

        // Fallback to standard password_verify
        return password_verify($password, $storedHash);
    }

    /**
     * Base64URL encode a binary or text string (RFC 7515).
     */
    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64URL decode a base64url-encoded string.
     */
    public static function base64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    /**
     * Cryptographically serialize and sign user session data into an HMAC-SHA256 token.
     * Output format: {base64url_json_payload}.{base64url_hmac_signature}
     *
     * @param array<string, mixed> $sessionData
     */
    public static function createSessionToken(array $sessionData): string
    {
        $secret = self::getSessionSecret();
        $payloadJson = json_encode($sessionData, JSON_UNESCAPED_SLASHES);
        $payloadEncoded = self::base64UrlEncode($payloadJson);

        $signatureRaw = hash_hmac('sha256', $payloadEncoded, $secret, true);
        $signatureEncoded = self::base64UrlEncode($signatureRaw);

        return "{$payloadEncoded}.{$signatureEncoded}";
    }

    /**
     * Cryptographically verify and deserialize an HMAC-SHA256 signed session token.
     * Returns the decoded array if valid, or null if invalid or tampered.
     *
     * @return array<string, mixed>|null
     */
    public static function verifySessionToken(?string $token): ?array
    {
        if (empty($token) || ! str_contains($token, '.')) {
            return null;
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadEncoded, $signatureEncoded] = $parts;
        if (empty($payloadEncoded) || empty($signatureEncoded)) {
            return null;
        }

        $secret = self::getSessionSecret();
        $expectedSignatureRaw = hash_hmac('sha256', $payloadEncoded, $secret, true);
        $expectedSignatureEncoded = self::base64UrlEncode($expectedSignatureRaw);

        if (! hash_equals($expectedSignatureEncoded, $signatureEncoded)) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($payloadEncoded);
        if ($payloadJson === null) {
            return null;
        }

        $decoded = json_decode($payloadJson, true);
        if (! is_array($decoded) || empty($decoded['id']) || empty($decoded['email'])) {
            return null;
        }

        return $decoded;
    }
}
