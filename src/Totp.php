<?php

declare(strict_types=1);

namespace EzPhp\TwoFactor;

/**
 * Class Totp
 *
 * RFC 6238 Time-Based One-Time Password (TOTP) implementation.
 *
 * Compatible with Google Authenticator, Authy, and any RFC 6238-compliant app.
 * Uses HMAC-SHA1, a 30-second period, and 6-digit codes by default.
 *
 * @package EzPhp\TwoFactor
 */
final class Totp
{
    /**
     * Generate a TOTP code for the given secret and Unix timestamp.
     *
     * @param string $secret    Base32-encoded shared secret.
     * @param int    $timestamp Unix timestamp (defaults to now).
     * @param int    $period    Time step in seconds (default: 30).
     * @param int    $digits    Number of digits in the output code (default: 6).
     */
    public static function generate(
        string $secret,
        int $timestamp,
        int $period = 30,
        int $digits = 6,
    ): string {
        $timeStep = (int) floor($timestamp / $period);

        // 8-byte big-endian unsigned 64-bit integer (RFC 6238 §4)
        $message = pack('J', $timeStep);

        $key = Base32::decode($secret);

        // HMAC-SHA1: 20-byte binary digest
        $hash = hash_hmac('sha1', $message, $key, true);

        // Dynamic truncation (RFC 4226 §5.3)
        $offset = ord($hash[19]) & 0x0f;

        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        );

        $otp = $code % (10 ** $digits);

        return str_pad((string) $otp, $digits, '0', STR_PAD_LEFT);
    }
}
