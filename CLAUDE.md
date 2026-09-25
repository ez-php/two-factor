# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
4. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
5. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented
- Concrete classes are `final` — extend behavior through composition, not inheritance. Exception-hierarchy base classes (e.g. `EzPhpException`, `HttpException`, `CacheException`) are one carve-out, since they exist specifically to be extended. A documented template-method-style base class (e.g. `Mailable`, meant to be configured via constructor-time subclassing) is the other — the owning module's `CLAUDE.md` must record it under Design Decisions.

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` (each `-`-separated word upper-cased) unless `--namespace=`
overrides it. Existing exceptions the guess gets wrong: `bignum` → `BigNum`,
`dataloader` → `DataLoader`, `dotenv` → `Env`, `graphql` → `GraphQL`, `oauth` → `OAuth`,
`opcache` → `OPCache`, `swagger-ui` → `SwaggerUI`, `webauthn` → `WebAuthn` and
`websocket` → `WebSocket`; `websocket-client` → `WebsocketClient`, `websocket-tls` → `WebsocketTls`,
`webauthn-metadata` → `WebauthnMetadata` and `metrics-statsd` → `MetricsStatsd` are
intentional lower-case-word namespaces, and `testing-application` shares `EzPhp\Testing\`
with `testing`).

To bring in a module whose code already lives in its own repository instead of
generating a fresh skeleton, pass `--repo=` with a git URL:

```
php make_module.php <name> --repo=<git-url> [--namespace=Foo]
```

This runs `git submodule add <url> modules/<name>` instead of writing package
files, then applies the same monorepo wiring below. It is mutually exclusive
with `--services` and `--description` — a submodule brings its own Docker
scaffold (if any) and its own `composer.json` description. A minimal `CLAUDE.md`
stub is written only if the submodule doesn't already ship one, so
`composer guidelines:sync` has a `# Package:` heading to anchor part 1 against.

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4` **and** the shared
`autoload-dev` `Tests\` directory list), `phpstan.neon`, `phpunit.xml` (test suite
**and** coverage source), and `packages.sh` (alphabetical position) — in both
generated and `--repo` mode.

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — claim the "next free" row by
  editing the table in `CODING_GUIDELINES.md` (never in a `CLAUDE.md` copy) and run
  `composer guidelines:sync` in the same change. Editing it drifts every `CLAUDE.md`
  until the sync runs, which is why the generator only reminds you instead of doing
  it. Skipping the edit leaves "next free" stale, so the next module collides.

### 4 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^2.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
```

Pass `--extensions` to merge PHP extension install blocks (apt packages plus `docker-php-ext-install`/`pecl` lines) directly into `docker/app/Dockerfile`, instead of hand-editing it afterward — supported extensions: `bcmath`, `gmp`, `gd`, `imagick`:

```
vendor/bin/docker-init --extensions=gmp,bcmath
vendor/bin/docker-init --extensions=gd,imagick
```

When run from a module directory inside this monorepo, any requested extension not already present is also merged into the shared root `docker/app/Dockerfile` — the container `composer full` at the root actually runs against, distinct from the module's own standalone image.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | Redis host port | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 (`REDIS_PORT`) | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/` (application template) | 3308 | 6383 (`REDIS_PORT`) | — |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 (`REDIS_HOST_PORT`) | — |
| `ez-php/queue` | 3310 | 6381 (`REDIS_HOST_PORT`) | — |
| `ez-php/rate-limiter` | — | 6382 (`REDIS_HOST_PORT`) | — |
| `ez-php/search` | — | — | 7701 |
| `ez-php/event-store` | 3311 | — | — |
| **next free** | **3312** | **6384** | **7702** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

> The "Redis host port" column is likewise the **host**-published port. `ez-php/cache`, `ez-php/queue`, and `ez-php/rate-limiter` map it through a separate `REDIS_HOST_PORT` env var in `docker-compose.yml`, keeping `REDIS_PORT` fixed at `6379` for in-container connections (the app container always reaches Redis at `redis:6379` over the Compose network, regardless of the host mapping) — the root project and the `ez-php/` application template are the two exceptions, since both have no host/container split and use `REDIS_PORT` for both (the template's other in-container Redis settings — `CACHE_REDIS_PORT`, `QUEUE_REDIS_PORT`, `RATE_LIMITER_REDIS_PORT`, `HEALTH_REDIS_PORT` — stay fixed at `6379` regardless, same as every other module).

> This table tracks only MySQL, Redis, and Meilisearch ports — the three services shared across multiple modules where a collision is otherwise easy to introduce. Mailpit is the one other service with published host ports: SMTP `1025` and web UI `8025`. `ez-php/mail` maps them through `MAILPIT_SMTP_HOST_PORT`/`MAILPIT_API_HOST_PORT` in `modules/mail/docker-compose.yml` (mirroring the `*_HOST_PORT` pattern above, documented in `modules/mail/.env.example`); the root project and the `ez-php/` template each run their own Mailpit on the same defaults (`MAIL_PORT`/`MAIL_WEB_PORT`), so **these three stacks cannot run at the same time** without overriding those variables. It isn't a table column because no module beyond those three runs Mailpit — but a new module adding its own single-use service's ports should likewise parameterize them and document the defaults in its own `.env.example` rather than adding a column here.

### 5 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/two-factor

RFC 6238 TOTP two-factor authentication for ez-php. Pure PHP — no external SDK. Provides secret generation, QR code URL building, code verification with clock-skew tolerance, backup codes, and HTTP middleware that enforces 2FA on protected routes.

---

## Source structure

```
src/
  Base32.php                        — RFC 4648 Base32 encode/decode (pure PHP)
  Totp.php                          — RFC 6238 TOTP code generation (HMAC-SHA1)
  TwoFactorAuthenticableInterface.php — User contract: hasTwoFactorEnabled(), getTwoFactorSecret()
  TwoFactorManager.php              — High-level API: generateSecret, verifyCode, QR URL, backup codes
  TwoFactorMiddleware.php           — HTTP middleware: returns 423 when 2FA required but unverified
  TwoFactorServiceProvider.php      — Binds TwoFactorManager to the container

tests/
  TestCase.php                      — Base test case (extends PHPUnit\Framework\TestCase)
  Base32Test.php                    — Encode/decode roundtrip, known RFC 4648 vector
  TotpTest.php                      — RFC 6238 Appendix B test vectors, determinism, period boundaries
  TwoFactorManagerTest.php          — Full manager API coverage
  TwoFactorMiddlewareTest.php       — Middleware pass-through and 423 scenarios
```

---

## Key classes and their responsibilities

### Base32 (`src/Base32.php`)

Static utility class. Implements RFC 4648 Base32 encoding using the `ABCDEFGHIJKLMNOPQRSTUVWXYZ234567` alphabet. Uses a streaming 5-bit accumulator for both encoding and decoding. Decode is case-insensitive and ignores `=` padding characters. No external dependencies.

---

### Totp (`src/Totp.php`)

Static utility class. Implements RFC 6238 TOTP code generation:

1. Computes `$timeStep = floor($timestamp / $period)`
2. Packs the 64-bit big-endian time step with `pack('J', $timeStep)`
3. Computes `hash_hmac('sha1', $message, $key, true)` where `$key = Base32::decode($secret)`
4. Applies RFC 4226 dynamic truncation: `$offset = ord($hash[19]) & 0x0f`
5. Extracts a 31-bit integer from bytes at `$offset` through `$offset+3`
6. Returns `$otp % (10 ** $digits)` zero-padded to `$digits` characters

Verified against RFC 6238 Appendix B test vectors (T=59 → `94287082`, T=1111111109 → `07081804`, T=1111111111 → `14050471`).

---

### TwoFactorAuthenticableInterface (`src/TwoFactorAuthenticableInterface.php`)

User contract. Implemented alongside `UserInterface` on user models that support 2FA. Two methods: `hasTwoFactorEnabled(): bool` and `getTwoFactorSecret(): string`. The middleware uses an `instanceof` check against this interface — users without it pass through unconditionally.

---

### TwoFactorManager (`src/TwoFactorManager.php`)

High-level service. No constructor dependencies. Wraps `Base32`, `Totp`, and PHP's `password_hash` / `password_verify`.

- `generateSecret()` — 10 random bytes → 16-character Base32 string (80 bits of entropy)
- `generateCode($secret, $timestamp)` — delegates to `Totp::generate()` with period=30, digits=6
- `verifyCode($secret, $code, $timestamp)` — loops `WINDOW = ±1` steps, uses `hash_equals` for timing safety
- `getQrCodeUrl($issuer, $account, $secret)` — builds `otpauth://totp/` URI; both issuer and account are `rawurlencode`-d; parameters added via `http_build_query`
- `generateBackupCodes($count)` — 4 random bytes per code, formatted as `XXXX-XXXX` uppercase hex
- `hashBackupCode($code)` / `verifyBackupCode($code, $hash)` — bcrypt (`PASSWORD_BCRYPT`) via `password_hash` / `password_verify`
- `verifyForUser($user, $code, ...)` — verifies a code against a `TwoFactorAuthenticableInterface` user and returns `EzPhp\Contracts\SecondFactorResult` (`NotConfigured` if `hasTwoFactorEnabled()` is false, else `Satisfied`/`NotSatisfied` per `verifyCode()`); see Design Decisions

---

### TwoFactorMiddleware (`src/TwoFactorMiddleware.php`)

Implements `MiddlewareInterface`. Placed **after** `AuthMiddleware` in the stack.

Pass-through conditions (returns `$next($request)`):
- `Auth::user()` returns `null`
- User does not implement `TwoFactorAuthenticableInterface`
- `$user->hasTwoFactorEnabled()` returns `false`
- `session_status() === PHP_SESSION_ACTIVE` and `$_SESSION[SESSION_KEY] === true`

All other cases with 2FA enabled: returns `Response('...', 423)` with headers `Content-Type: text/plain` and `X-Requires-2FA: true`.

`SESSION_KEY = 'two_factor_verified'` — set this to `true` in your OTP verification controller on success; unset on logout.

---

### TwoFactorServiceProvider (`src/TwoFactorServiceProvider.php`)

Binds `TwoFactorManager` in `register()`. No-op `boot()`. No configuration required — `TwoFactorManager` has no constructor dependencies.

---

## Design decisions and constraints

- **Pure PHP, no external SDK.** TOTP is a well-specified algorithm (RFC 6238 + RFC 4226). Implementing it in ~50 lines of PHP is straightforward and removes any third-party runtime dependency. The only non-obvious part is `pack('J', $timeStep)` for the 8-byte big-endian time step message.
- **Base32 implemented inline.** Google Authenticator and Authy use Base32-encoded secrets. PHP has no built-in Base32 support. The implementation is self-contained and tested against the RFC 4648 known vector (`12345678901234567890` → `GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ`).
- **Clock-skew window of ±1 step (30 seconds).** This is the standard tolerance. A window of 0 would reject valid codes from users with slightly drifted clocks; a window of 2+ would increase replay-attack exposure. ±1 is the TOTP spec recommendation.
- **`hash_equals` for code comparison.** TOTP codes are short strings. Comparing them with `===` is safe from an information-leakage standpoint (the code space is only 10^6), but `hash_equals` is the defensive-correct choice and has zero cost.
- **Backup codes use bcrypt.** Backup codes are high-value secrets. `PASSWORD_BCRYPT` is appropriate for storage. The module does not track which backup codes have been used — that is the application's responsibility (delete or mark the hash as consumed after verification).
- **423 Locked status code.** RFC 4918 defines 423 Locked as "the resource that is being accessed is locked". It is the correct status for "you are authenticated but blocked pending a second factor". Some implementations use 403 Forbidden, but 423 is more semantically accurate and allows clients to distinguish between "forbidden forever" (403) and "temporarily blocked pending 2FA" (423).
- **No QR image generation.** Generating the actual QR code image requires a library (e.g. `endroid/qr-code`). This module intentionally returns only the `otpauth://` URI — the application chooses how to render it. This keeps the module's dependency footprint minimal.
- **Session-based verification state.** The middleware checks `$_SESSION[SESSION_KEY]`. This is the standard approach for web applications. API/SPA applications may prefer a token-based approach — they can bypass the session check by implementing their own middleware and calling `TwoFactorManager::verifyCode()` directly.
- **Depends on `ez-php/auth`.** The middleware calls `Auth::user()` to get the currently authenticated user. This couples the module to `ez-php/auth`. Applications not using the auth module should bypass the middleware and call `TwoFactorManager` directly.
- **`verifyForUser()` returns `EzPhp\Contracts\SecondFactorResult`, not a bool.** `ez-php/webauthn` verifies a structurally different second factor (a passkey assertion, not a submitted code) and cannot share `verifyCode()`'s signature — but both modules can report a uniform `Satisfied`/`NotSatisfied`/`NotConfigured` outcome via this shared contracts-level enum, letting application login-flow code branch on one type regardless of which mechanism ran. This does not merge the two modules — `ez-php/webauthn` remains excluded above, and `ez-php/contracts` (already a hard dependency of this module) carries no logic of its own, only the enum. `verifyCode()` itself is unchanged; `verifyForUser()` is an additive wrapper.

---

## Testing approach

No external infrastructure required — all tests run in-process.

- `Base32Test` — roundtrip with random bytes, known RFC 4648 test vector, padding tolerance, case-insensitivity, empty string edge case.
- `TotpTest` — three RFC 6238 Appendix B test vectors (8-digit, SHA-1), digit count validation, determinism within the same time step, different secrets produce different codes, codes change between 30-second periods.
- `TwoFactorManagerTest` — secret format/length/randomness, `verifyCode` accepts current/previous/next window, rejects expired and wrong codes, `getQrCodeUrl` format and special-character encoding, backup code count/format/uniqueness, bcrypt hash roundtrip and wrong-code rejection.
- `TwoFactorMiddlewareTest` — uses `Auth::resetInstance()` / `Auth::setInstance(new Auth(null))` / `Auth::login($user)` to control auth state without a database; intersection type `UserInterface&TwoFactorAuthenticableInterface` for two-factor test users; `$_SESSION` manipulation for session state scenarios; `setUp`/`tearDown` clean up both `Auth` singleton and session state.

---

## What does not belong in this module

- **QR code image generation** — use a library like `endroid/qr-code` in the application layer; this module provides the `otpauth://` URI only.
- **Backup code storage and usage tracking** — the application manages the database; this module provides generation, hashing, and verification only.
- **HOTP (counter-based OTP)** — HOTP requires server-side counter synchronisation state; out of scope.
- **WebAuthn / FIDO2** — hardware key / passkey authentication is a fundamentally different protocol; belongs in a separate module.
- **SMS / email OTP delivery** — use `ez-php/notification` for out-of-band delivery; this module implements TOTP (app-based) only.
- **Recovery flow / account unlock** — application-level concern; this module provides the building blocks.
- **Rate limiting on OTP attempts** — apply `ez-php/rate-limiter`'s `ThrottleMiddleware` to the verification endpoint.
