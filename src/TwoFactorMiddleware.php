<?php

declare(strict_types=1);

namespace EzPhp\TwoFactor;

use EzPhp\Auth\Auth;
use EzPhp\Contracts\MiddlewareInterface;
use EzPhp\Http\Request;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;

/**
 * Class TwoFactorMiddleware
 *
 * Enforces two-factor authentication for routes that require it.
 *
 * Place this middleware **after** `AuthMiddleware` in the stack. It checks
 * whether the authenticated user has 2FA enabled and whether the current
 * session has been verified. If 2FA is required but not yet verified, the
 * middleware returns `423 Locked`.
 *
 * The application is responsible for:
 *   - Providing a route/controller that accepts and verifies the OTP
 *   - Setting `$_SESSION[TwoFactorMiddleware::SESSION_KEY] = true` on success
 *   - Unsetting the key on logout
 *
 * Only users that implement `TwoFactorAuthenticableInterface` are subject to
 * this middleware. Users without the interface (or with 2FA disabled) pass
 * through unconditionally.
 *
 * @package EzPhp\TwoFactor
 */
final class TwoFactorMiddleware implements MiddlewareInterface
{
    /**
     * Session key used to mark a session as 2FA-verified.
     *
     * Set this to `true` after successful OTP verification in your controller:
     *
     *   $_SESSION[TwoFactorMiddleware::SESSION_KEY] = true;
     */
    public const SESSION_KEY = 'two_factor_verified';

    /**
     * Handle the request.
     *
     * Passes through when:
     *   - No user is authenticated
     *   - The authenticated user does not implement `TwoFactorAuthenticableInterface`
     *   - The user has 2FA disabled
     *   - The current session already has a valid 2FA verification
     *
     * Returns `423 Locked` when 2FA is required but not yet verified.
     *
     * @param RequestInterface $request
     * @param callable(RequestInterface): Response $next
     */
    public function handle(RequestInterface $request, callable $next): Response
    {
        $user = Auth::user();

        if ($user === null || !($user instanceof TwoFactorAuthenticableInterface)) {
            return $next($request);
        }

        if (!$user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if (
            session_status() === PHP_SESSION_ACTIVE
            && isset($_SESSION[self::SESSION_KEY])
            && $_SESSION[self::SESSION_KEY] === true
        ) {
            return $next($request);
        }

        return (new Response('Two-factor authentication required.', 423))
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->withHeader('X-Requires-2FA', 'true');
    }
}
