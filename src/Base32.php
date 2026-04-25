<?php

declare(strict_types=1);

namespace EzPhp\TwoFactor;

/**
 * Class Base32
 *
 * RFC 4648 Base32 encoder/decoder using the standard alphabet (A–Z, 2–7).
 *
 * Used by TOTP implementations to encode shared secrets in a format compatible
 * with Google Authenticator, Authy, and other OTP clients.
 *
 * @package EzPhp\TwoFactor
 */
final class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Encode binary data to a Base32 string (no padding).
     */
    public static function encode(string $bytes): string
    {
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;
        $length = strlen($bytes);

        for ($i = 0; $i < $length; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bitsLeft += 8;

            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $output .= self::ALPHABET[($buffer >> $bitsLeft) & 0x1f];
            }
        }

        if ($bitsLeft > 0) {
            $output .= self::ALPHABET[($buffer << (5 - $bitsLeft)) & 0x1f];
        }

        return $output;
    }

    /**
     * Decode a Base32 string to binary data.
     *
     * Padding characters (`=`) and whitespace are ignored.
     * Invalid characters are silently skipped.
     */
    public static function decode(string $encoded): string
    {
        $encoded = strtoupper(rtrim($encoded, '='));
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;
        $length = strlen($encoded);

        for ($i = 0; $i < $length; $i++) {
            $charValue = self::charValue($encoded[$i]);

            if ($charValue === null) {
                continue;
            }

            $buffer = ($buffer << 5) | $charValue;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xff);
            }
        }

        return $output;
    }

    /**
     * Return the 5-bit integer value of a Base32 character, or null for invalid characters.
     */
    private static function charValue(string $char): ?int
    {
        $pos = strpos(self::ALPHABET, $char);

        return $pos === false ? null : $pos;
    }
}
