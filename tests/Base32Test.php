<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\TwoFactor\Base32;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Base32::class)]
final class Base32Test extends TestCase
{
    public function testEncodeDecodeRoundtrip(): void
    {
        $bytes = random_bytes(20);

        $encoded = Base32::encode($bytes);
        $decoded = Base32::decode($encoded);

        self::assertSame($bytes, $decoded);
    }

    public function testKnownVector(): void
    {
        // ASCII "12345678901234567890" → known Base32 encoding
        self::assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', Base32::encode('12345678901234567890'));
    }

    public function testDecodeKnownVector(): void
    {
        self::assertSame('12345678901234567890', Base32::decode('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'));
    }

    public function testDecodeIgnoresPaddingChars(): void
    {
        $withPadding = 'MFRA====';
        $withoutPadding = 'MFRA';

        self::assertSame(Base32::decode($withoutPadding), Base32::decode($withPadding));
    }

    public function testDecodeIsCaseInsensitive(): void
    {
        self::assertSame(Base32::decode('MFRA'), Base32::decode('mfra'));
    }

    public function testEncodedStringContainsOnlyValidCharacters(): void
    {
        $encoded = Base32::encode(random_bytes(15));

        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $encoded);
    }

    public function testEmptyStringRoundtrip(): void
    {
        self::assertSame('', Base32::encode(''));
        self::assertSame('', Base32::decode(''));
    }
}
