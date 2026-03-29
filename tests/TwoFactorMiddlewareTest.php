<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Auth\Auth;
use EzPhp\Auth\UserInterface;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\TwoFactor\TwoFactorAuthenticableInterface;
use EzPhp\TwoFactor\TwoFactorMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TwoFactorMiddleware::class)]
final class TwoFactorMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        Auth::resetInstance();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    protected function tearDown(): void
    {
        Auth::resetInstance();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    public function testPassesThroughWhenNoUserAuthenticated(): void
    {
        $response = $this->callMiddleware($this->makeRequest());

        self::assertSame(200, $response->status());
    }

    public function testPassesThroughWhenUserDoesNotImplementInterface(): void
    {
        $user = $this->makeBasicUser();
        $this->loginUser($user);

        $response = $this->callMiddleware($this->makeRequest());

        self::assertSame(200, $response->status());
    }

    public function testPassesThroughWhenTwoFactorDisabled(): void
    {
        $user = $this->makeTwoFactorUser(enabled: false);
        $this->loginUser($user);

        $response = $this->callMiddleware($this->makeRequest());

        self::assertSame(200, $response->status());
    }

    public function testReturns423WhenTwoFactorRequiredAndNotVerified(): void
    {
        $user = $this->makeTwoFactorUser(enabled: true);
        $this->loginUser($user);

        $response = $this->callMiddleware($this->makeRequest());

        self::assertSame(423, $response->status());
    }

    public function testPassesThroughWhenSessionAlreadyVerified(): void
    {
        $user = $this->makeTwoFactorUser(enabled: true);
        $this->loginUser($user);

        session_start();
        $_SESSION[TwoFactorMiddleware::SESSION_KEY] = true;

        $response = $this->callMiddleware($this->makeRequest());

        self::assertSame(200, $response->status());
    }

    public function testReturns423WhenSessionExistsButNotVerified(): void
    {
        $user = $this->makeTwoFactorUser(enabled: true);
        $this->loginUser($user);

        session_start();
        // Session is active but 2FA key is not set

        $response = $this->callMiddleware($this->makeRequest());

        self::assertSame(423, $response->status());
    }

    public function test423ResponseHasRequires2FAHeader(): void
    {
        $user = $this->makeTwoFactorUser(enabled: true);
        $this->loginUser($user);

        $response = $this->callMiddleware($this->makeRequest());

        self::assertArrayHasKey('X-Requires-2FA', $response->headers());
    }

    private function callMiddleware(Request $request): Response
    {
        $middleware = new TwoFactorMiddleware();
        return $middleware->handle($request, fn (): Response => new Response('ok', 200));
    }

    private function makeRequest(): Request
    {
        return new Request('GET', '/dashboard');
    }

    private function loginUser(UserInterface $user): void
    {
        $auth = new Auth(null);
        Auth::setInstance($auth);
        Auth::login($user);
    }

    private function makeBasicUser(): UserInterface
    {
        return new readonly class () implements UserInterface {
            public function getAuthId(): int
            {
                return 1;
            }

            public function getAuthPassword(): string
            {
                return 'hash';
            }
        };
    }

    private function makeTwoFactorUser(bool $enabled): UserInterface&TwoFactorAuthenticableInterface
    {
        return new readonly class ($enabled) implements UserInterface, TwoFactorAuthenticableInterface {
            public function __construct(private bool $enabled)
            {
            }

            public function getAuthId(): int
            {
                return 1;
            }

            public function getAuthPassword(): string
            {
                return 'hash';
            }

            public function hasTwoFactorEnabled(): bool
            {
                return $this->enabled;
            }

            public function getTwoFactorSecret(): string
            {
                return 'JBSWY3DPEHPK3PXP';
            }
        };
    }
}
