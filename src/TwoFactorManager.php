<?php

declare(strict_types=1);

namespace EzPhp\TwoFactor;

/**
 * Class TwoFactorManager
 *
 * High-level API for RFC 6238 TOTP two-factor authentication.
 *
 * Responsibilities:
 *   - Secret generation (cryptographically random, Base32-encoded)
 *   - Code generation and verification (±1 time-step window for clock skew)
 *   - QR code URI generation (otpauth:// for Google Authenticator / Authy)
 *   - Backup code generation, hashing, and verification
 *
 * @package EzPhp\TwoFactor
 */
final class TwoFactorManager
{
    /**
     * Number of time steps to check on each side of the current step (clock skew tolerance).
     * A window of 1 allows codes from T-30s to T+30s.
     */
    private const WINDOW = 1;

    /**
     * TOTP period in seconds.
     */
    private const PERIOD = 30;

    /**
     * Number of digits in the generated code.
     */
    private const DIGITS = 6;

    /**
     * Generate a cryptographically random Base32-encoded shared secret.
     *
     * The returned secret is 16 characters long (80 bits of entropy),
     * suitable for use with Google Authenticator and all RFC 6238-compliant apps.
     */
    public function generateSecret(): string
    {
        return Base32::encode(random_bytes(10));
    }

    /**
     * Generate the current TOTP code for a given secret.
     *
     * @param string   $secret    Base32-encoded shared secret.
     * @param int|null $timestamp Unix timestamp. Defaults to `time()`.
     */
    public function generateCode(string $secret, ?int $timestamp = null): string
    {
        return Totp::generate($secret, $timestamp ?? time(), self::PERIOD, self::DIGITS);
    }

    /**
     * Verify a TOTP code against a shared secret.
     *
     * Checks the current time step and ±WINDOW steps to tolerate clock skew
     * between the server and the user's authenticator app.
     *
     * Uses `hash_equals` for timing-safe comparison.
     *
     * @param string   $secret    Base32-encoded shared secret.
     * @param string   $code      6-digit code submitted by the user.
     * @param int|null $timestamp Unix timestamp. Defaults to `time()`.
     */
    public function verifyCode(string $secret, string $code, ?int $timestamp = null): bool
    {
        $time = $timestamp ?? time();

        for ($step = -self::WINDOW; $step <= self::WINDOW; $step++) {
            $expected = Totp::generate($secret, $time + ($step * self::PERIOD), self::PERIOD, self::DIGITS);

            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build an `otpauth://totp/` URI for display as a QR code.
     *
     * The URI can be encoded as a QR code and scanned by Google Authenticator,
     * Authy, or any compatible app. This method returns the URI only — QR image
     * generation requires a separate library or API call.
     *
     * @param string $issuer      Application name shown in the authenticator app.
     * @param string $accountName User identifier shown below the issuer (e.g. email address).
     * @param string $secret      Base32-encoded shared secret.
     */
    public function getQrCodeUrl(string $issuer, string $accountName, string $secret): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($accountName);

        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);

        return 'otpauth://totp/' . $label . '?' . $params;
    }

    /**
     * Generate a set of single-use backup codes.
     *
     * Each code is formatted as `XXXX-XXXX` (uppercase hex).
     * Store the hashed versions (via `hashBackupCode()`) — never store plaintext.
     *
     * @param int $count Number of codes to generate (default: 8).
     *
     * @return string[] Plain-text backup codes to show the user once.
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $hex = bin2hex(random_bytes(4));
            $codes[] = strtoupper(substr($hex, 0, 4) . '-' . substr($hex, 4, 4));
        }

        return $codes;
    }

    /**
     * Hash a backup code for safe storage using bcrypt.
     *
     * Store the returned hash in your database. Never store the plain-text code
     * after displaying it to the user.
     */
    public function hashBackupCode(string $code): string
    {
        return password_hash($code, PASSWORD_BCRYPT);
    }

    /**
     * Verify a backup code against a stored bcrypt hash.
     *
     * After a successful verification the application must mark the backup code
     * as consumed — this module does not track usage state.
     */
    public function verifyBackupCode(string $code, string $hash): bool
    {
        return password_verify($code, $hash);
    }
}
