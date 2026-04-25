<?php

declare(strict_types=1);

namespace EzPhp\TwoFactor;

/**
 * Interface TwoFactorAuthenticableInterface
 *
 * Optional contract for user models that support two-factor authentication.
 *
 * Implement this interface on your user model to enable `TwoFactorMiddleware`
 * to enforce 2FA verification after successful primary authentication.
 *
 * Usage in user model:
 *
 *   class User implements UserInterface, TwoFactorAuthenticableInterface
 *   {
 *       public function hasTwoFactorEnabled(): bool
 *       {
 *           return $this->two_factor_secret !== null;
 *       }
 *
 *       public function getTwoFactorSecret(): string
 *       {
 *           return $this->two_factor_secret;
 *       }
 *   }
 *
 * @package EzPhp\TwoFactor
 */
interface TwoFactorAuthenticableInterface
{
    /**
     * Returns true when two-factor authentication is active for this user.
     */
    public function hasTwoFactorEnabled(): bool;

    /**
     * Returns the Base32-encoded TOTP shared secret for this user.
     *
     * Only called when `hasTwoFactorEnabled()` returns true.
     */
    public function getTwoFactorSecret(): string;
}
