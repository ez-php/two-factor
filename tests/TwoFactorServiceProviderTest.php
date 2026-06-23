<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\TwoFactor\TwoFactorManager;
use EzPhp\TwoFactor\TwoFactorServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\FakeConfig;
use Tests\Support\FakeContainer;

/**
 * Smoke test: TwoFactorServiceProvider registers its binding in a minimal
 * container context without error.
 *
 * @uses \Tests\Support\FakeConfig
 * @uses \Tests\Support\FakeContainer
 */
#[CoversClass(TwoFactorServiceProvider::class)]
#[UsesClass(TwoFactorManager::class)]
final class TwoFactorServiceProviderTest extends TestCase
{
    public function test_register_binds_two_factor_manager(): void
    {
        $container = new FakeContainer(new FakeConfig([]));
        $provider = new TwoFactorServiceProvider($container);

        $provider->register();
        $provider->boot(); // no-op, must not throw

        $this->assertTrue($container->wasBound(TwoFactorManager::class));
        $this->assertInstanceOf(TwoFactorManager::class, $container->make(TwoFactorManager::class));
    }
}
