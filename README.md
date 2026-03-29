# ez-php/two-factor

Two-factor authentication module for the ez-php framework. Provides RFC 6238 TOTP (Time-based One-Time Password) support with pure PHP — no external SDK required. Includes secret generation, QR code URL generation, code verification, backup codes, and HTTP middleware.

## Installation

```bash
composer require ez-php/two-factor
```

## Setup

### 1. Implement the interface on your user model

```php
use EzPhp\Auth\UserInterface;
use EzPhp\TwoFactor\TwoFactorAuthenticableInterface;

final class User implements UserInterface, TwoFactorAuthenticableInterface
{
    public function hasTwoFactorEnabled(): bool
    {
        return (bool) $this->two_factor_enabled;
    }

    public function getTwoFactorSecret(): string
    {
        return $this->two_factor_secret;
    }

    // ... UserInterface methods
}
```

### 2. Register the service provider

In `provider/modules.php`:

```php
$app->register(\EzPhp\TwoFactor\TwoFactorServiceProvider::class);
```

### 3. Add the middleware

Apply `TwoFactorMiddleware` to routes that require 2FA verification:

```php
// routes/web.php
$router->group(['middleware' => [TwoFactorMiddleware::class]], function ($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
});
```

## Usage

### Enabling 2FA for a user

```php
use EzPhp\TwoFactor\TwoFactorManager;

$manager = $container->make(TwoFactorManager::class);

// Generate and store the secret
$secret = $manager->generateSecret();
// → store $secret in your user record (two_factor_secret column)

// Get QR code URL to display to the user
$qrUrl = $manager->getQrCodeUrl('MyApp', $user->email, $secret);
// → render into a QR code image using your preferred library
```

### Verifying the setup code

```php
// User scans QR code and enters the first code from their app
if ($manager->verifyCode($secret, $request->input('code'))) {
    // Enable 2FA for the user
    $user->update(['two_factor_enabled' => true, 'two_factor_secret' => $secret]);
}
```

### Verifying during login

After the user is authenticated, mark the session as verified:

```php
// In your 2FA verification controller
if ($manager->verifyCode(Auth::user()->getTwoFactorSecret(), $request->input('code'))) {
    $_SESSION[TwoFactorMiddleware::SESSION_KEY] = true;
    return redirect('/dashboard');
}
```

### Backup codes

```php
// Generate backup codes (store hashes, show plain codes to user once)
$codes = $manager->generateBackupCodes(8);
foreach ($codes as $code) {
    $hashes[] = $manager->hashBackupCode($code);
}
// Store $hashes in the database

// Verify a backup code on login
foreach ($storedHashes as $hash) {
    if ($manager->verifyBackupCode($inputCode, $hash)) {
        // Valid — invalidate this backup code
        break;
    }
}
```

## Middleware Behaviour

`TwoFactorMiddleware` runs on every request passing through it:

| Condition | Result |
|---|---|
| No authenticated user | Pass through (200) |
| User does not implement `TwoFactorAuthenticableInterface` | Pass through (200) |
| User has 2FA disabled | Pass through (200) |
| Session contains `two_factor_verified = true` | Pass through (200) |
| 2FA required but not verified | `423 Locked` + `X-Requires-2FA: true` |

The `X-Requires-2FA: true` header signals to API clients that a 2FA verification step is needed.

## API Reference

### `TwoFactorManager`

| Method | Description |
|---|---|
| `generateSecret(): string` | Generates a 16-character Base32 secret (80 bits of entropy) |
| `generateCode(string $secret, ?int $timestamp = null): string` | Generates a 6-digit TOTP code |
| `verifyCode(string $secret, string $code, ?int $timestamp = null): bool` | Verifies a code with ±1 time step tolerance |
| `getQrCodeUrl(string $issuer, string $account, string $secret): string` | Returns an `otpauth://totp/...` URI for QR code generation |
| `generateBackupCodes(int $count = 8): string[]` | Generates `XXXX-XXXX` format backup codes |
| `hashBackupCode(string $code): string` | Bcrypt-hashes a backup code for storage |
| `verifyBackupCode(string $code, string $hash): bool` | Verifies a backup code against its hash |

### `TwoFactorMiddleware`

| Constant | Value |
|---|---|
| `SESSION_KEY` | `'two_factor_verified'` |

## Standards Compliance

- **RFC 6238** — TOTP: Time-Based One-Time Password Algorithm
- **RFC 4226** — HOTP: HMAC-Based One-Time Password Algorithm
- **RFC 4648** — Base32 encoding/decoding
