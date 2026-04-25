<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\TwoFactor\Base32;
use EzPhp\TwoFactor\Totp;
use EzPhp\TwoFactor\TwoFactorManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(TwoFactorManager::class)]
#[UsesClass(Base32::class)]
#[UsesClass(Totp::class)]
final class TwoFactorManagerTest extends TestCase
{
    private TwoFactorManager $manager;

    protected function setUp(): void
    {
        $this->manager = new TwoFactorManager();
    }

    public function testGenerateSecretReturnsBase32String(): void
    {
        $secret = $this->manager->generateSecret();

        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testGenerateSecretReturns16Characters(): void
    {
        $secret = $this->manager->generateSecret();

        // 10 random bytes → 16 Base32 characters (80 bits of entropy)
        self::assertSame(16, strlen($secret));
    }

    public function testGenerateSecretIsRandomEachCall(): void
    {
        $secret1 = $this->manager->generateSecret();
        $secret2 = $this->manager->generateSecret();

        self::assertNotSame($secret1, $secret2);
    }

    public function testVerifyCodeAcceptsCurrentCode(): void
    {
        $secret = $this->manager->generateSecret();
        $timestamp = 1_000_000;

        $code = $this->manager->generateCode($secret, $timestamp);

        self::assertTrue($this->manager->verifyCode($secret, $code, $timestamp));
    }

    public function testVerifyCodeAcceptsCodeFromPreviousWindow(): void
    {
        $secret = $this->manager->generateSecret();
        $timestamp = 1_000_030; // start of step N

        $previousCode = $this->manager->generateCode($secret, $timestamp - 30); // step N-1

        self::assertTrue($this->manager->verifyCode($secret, $previousCode, $timestamp));
    }

    public function testVerifyCodeAcceptsCodeFromNextWindow(): void
    {
        $secret = $this->manager->generateSecret();
        $timestamp = 1_000_000;

        $nextCode = $this->manager->generateCode($secret, $timestamp + 30);

        self::assertTrue($this->manager->verifyCode($secret, $nextCode, $timestamp));
    }

    public function testVerifyCodeRejectsExpiredCode(): void
    {
        $secret = $this->manager->generateSecret();
        $timestamp = 1_000_000;

        // Code from two steps ago (60 seconds) is outside the ±1 window
        $oldCode = $this->manager->generateCode($secret, $timestamp - 60);

        self::assertFalse($this->manager->verifyCode($secret, $oldCode, $timestamp));
    }

    public function testVerifyCodeRejectsWrongCode(): void
    {
        $secret = $this->manager->generateSecret();

        self::assertFalse($this->manager->verifyCode($secret, '000000'));
    }

    public function testVerifyCodeDefaultsToNow(): void
    {
        $secret = $this->manager->generateSecret();
        $code = $this->manager->generateCode($secret);

        self::assertTrue($this->manager->verifyCode($secret, $code));
    }

    public function testGetQrCodeUrlFormat(): void
    {
        $url = $this->manager->getQrCodeUrl('MyApp', 'user@example.com', 'JBSWY3DPEHPK3PXP');

        self::assertStringStartsWith('otpauth://totp/', $url);
        self::assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $url);
        self::assertStringContainsString('issuer=MyApp', $url);
        self::assertStringContainsString('algorithm=SHA1', $url);
        self::assertStringContainsString('digits=6', $url);
        self::assertStringContainsString('period=30', $url);
    }

    public function testGetQrCodeUrlEncodesSpecialCharacters(): void
    {
        $url = $this->manager->getQrCodeUrl('My App', 'user+test@example.com', 'SECRET');

        self::assertStringContainsString('My%20App', $url);
        self::assertStringContainsString('user%2Btest%40example.com', $url);
    }

    public function testGenerateBackupCodesReturnsCorrectCount(): void
    {
        $codes = $this->manager->generateBackupCodes(8);

        self::assertCount(8, $codes);
    }

    public function testGenerateBackupCodesHaveCorrectFormat(): void
    {
        $codes = $this->manager->generateBackupCodes(3);

        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[0-9A-F]{4}-[0-9A-F]{4}$/', $code);
        }
    }

    public function testGenerateBackupCodesAreUnique(): void
    {
        $codes = $this->manager->generateBackupCodes(8);

        self::assertSame(count($codes), count(array_unique($codes)));
    }

    public function testBackupCodeHashVerifyRoundtrip(): void
    {
        $code = 'ABCD-1234';
        $hash = $this->manager->hashBackupCode($code);

        self::assertTrue($this->manager->verifyBackupCode($code, $hash));
    }

    public function testBackupCodeVerifyRejectsWrongCode(): void
    {
        $hash = $this->manager->hashBackupCode('ABCD-1234');

        self::assertFalse($this->manager->verifyBackupCode('WXYZ-9999', $hash));
    }

    public function testBackupCodeHashIsNotPlaintext(): void
    {
        $code = 'ABCD-1234';
        $hash = $this->manager->hashBackupCode($code);

        self::assertNotSame($code, $hash);
        self::assertStringStartsWith('$2y$', $hash);
    }
}
