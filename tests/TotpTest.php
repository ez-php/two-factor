<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\TwoFactor\Base32;
use EzPhp\TwoFactor\Totp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Totp::class)]
#[UsesClass(Base32::class)]
final class TotpTest extends TestCase
{
    /**
     * RFC 6238 Appendix B test vector (SHA-1, 8-digit).
     *
     * Secret (ASCII): "12345678901234567890"
     * T=59 → time_step=1 → expected 8-digit code: 94287082
     */
    public function testRfc6238VectorAt59(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $code = Totp::generate($secret, 59, period: 30, digits: 8);

        self::assertSame('94287082', $code);
    }

    /**
     * RFC 6238 Appendix B test vector (SHA-1, 8-digit).
     *
     * T=1111111109 → time_step=37037037 → expected 8-digit code: 07081804
     */
    public function testRfc6238VectorAt1111111109(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $code = Totp::generate($secret, 1111111109, period: 30, digits: 8);

        self::assertSame('07081804', $code);
    }

    /**
     * RFC 6238 Appendix B test vector (SHA-1, 8-digit).
     *
     * T=1111111111 → time_step=37037037 → expected 8-digit code: 14050471
     */
    public function testRfc6238VectorAt1111111111(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $code = Totp::generate($secret, 1111111111, period: 30, digits: 8);

        self::assertSame('14050471', $code);
    }

    public function testGeneratesCorrectNumberOfDigits(): void
    {
        $secret = Base32::encode(random_bytes(10));

        $code6 = Totp::generate($secret, time());
        $code8 = Totp::generate($secret, time(), digits: 8);

        self::assertMatchesRegularExpression('/^\d{6}$/', $code6);
        self::assertMatchesRegularExpression('/^\d{8}$/', $code8);
    }

    public function testCodeIsDeterministicForSameTimestep(): void
    {
        $secret = Base32::encode(random_bytes(10));
        $timestamp = 1_000_000;

        $code1 = Totp::generate($secret, $timestamp);
        $code2 = Totp::generate($secret, $timestamp);

        self::assertSame($code1, $code2);
    }

    public function testDifferentSecretsProduceDifferentCodes(): void
    {
        $timestamp = 1_000_000;

        $code1 = Totp::generate(Base32::encode(random_bytes(10)), $timestamp);
        $code2 = Totp::generate(Base32::encode(random_bytes(10)), $timestamp);

        // Extremely unlikely to collide; if this flakes, investigate the PRNG
        self::assertNotSame($code1, $code2);
    }

    public function testCodesChangeBetweenPeriods(): void
    {
        $secret = Base32::encode(random_bytes(10));

        $code1 = Totp::generate($secret, 0);
        $code2 = Totp::generate($secret, 30);
        $code3 = Totp::generate($secret, 60);

        // Each 30-second window produces a different code
        self::assertNotSame($code1, $code2);
        self::assertNotSame($code2, $code3);
    }

    public function testCodesAreIdenticalWithinSamePeriod(): void
    {
        $secret = Base32::encode(random_bytes(10));

        // Timestamps 0–29 all fall in step 0
        $code1 = Totp::generate($secret, 0);
        $code2 = Totp::generate($secret, 29);

        self::assertSame($code1, $code2);
    }
}
