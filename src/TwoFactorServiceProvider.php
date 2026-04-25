<?php

declare(strict_types=1);

namespace EzPhp\TwoFactor;

use EzPhp\Contracts\ServiceProvider;

/**
 * Class TwoFactorServiceProvider
 *
 * Registers the TwoFactorManager in the application container.
 *
 * Register in provider/modules.php:
 *
 *   $app->register(TwoFactorServiceProvider::class);
 *
 * After registration, resolve the manager from the container:
 *
 *   $manager = $app->make(TwoFactorManager::class);
 *   $secret  = $manager->generateSecret();
 *
 * @package EzPhp\TwoFactor
 */
final class TwoFactorServiceProvider extends ServiceProvider
{
    /**
     * Bind TwoFactorManager into the container.
     */
    public function register(): void
    {
        $this->app->bind(TwoFactorManager::class, fn (): TwoFactorManager => new TwoFactorManager());
    }

    /**
     * Nothing to boot — this module has no HTTP endpoint and no static façade.
     */
    public function boot(): void
    {
    }
}
