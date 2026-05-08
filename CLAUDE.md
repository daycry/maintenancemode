# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer ci          # cs + analyze + psalm + rector + test (full local pipeline; CI runs each as a separate workflow)
composer test        # PHPUnit
composer cs          # PHP-CS-Fixer dry-run
composer cs-fix      # auto-apply style fixes
composer analyze     # PHPStan (level 6, config: phpstan.neon.dist)
composer psalm       # Psalm (errorLevel 6, config: psalm.xml.dist)
composer rector      # Rector dry-run (config: rector.php — DEAD_CODE set + curated rules, src/ only)
composer rector-fix  # apply Rector changes
```

Single test:
```bash
vendor/bin/phpunit --filter testCookieBypassRequiresCookieValueNotCookieName
vendor/bin/phpunit tests/Maintenance/SecurityTest.php
```

The package autoloads `src/Helpers/maintenance_helper.php` via composer's `files` autoload — running `composer dump-autoload` after editing that helper is required.

## Architecture

This is a CodeIgniter 4 package, not an application. All code lives in `src/` under namespace `Daycry\Maintenance`.

```
HTTP request
    │
    ▼
Filters\Maintenance::before()
    │
    ▼
Controllers\Maintenance::check()        ← static entry point; CLI short-circuit lives here
    │
    ▼
Services\MaintenanceService::check()    ← single source of truth, returns CheckResult
    ├─ Storage\StorageInterface         ← FileStorage (flock + JSON_THROW) | CacheStorage (CI4 Cache)
    │   chosen by StorageFactory from   ← $config->driver = 'cache'|'file' (legacy $useCache fallback)
    │   Config\Maintenance
    └─ Libraries\IpChecker              ← IPv4/IPv6/CIDR matcher with bounded LRU cache
```

Persisted maintenance state is wrapped in the immutable `DTO\MaintenanceData` (snake_case properties to mirror the legacy `(object) json_decode` shape — Controllers and Views read `$data->cookie_name` etc. unchanged).

### Bypass evaluation order

`MaintenanceService::check()` returns the FIRST match in this exact order. Changes that reorder this MUST also update `docs/bypass.md` and `tests/Maintenance/Sprint3FeaturesTest.php`:

1. `isPending()` (scheduled-start in the future) → `CheckResult::pending()`, traffic flows.
2. `isExpired()` (scheduled-end in the past) → storage is **wiped** via `$this->storage->remove()`, event `maintenance.scheduled.expired` fires, treated as inactive.
3. `bypassRoutes` URI prefix match (with optional trailing `*`).
4. Config-level secret URL (`?maintenance_secret=` vs `$config->secretBypassKey`, timing-safe).
5. Window-level secret URL (vs `$data->secret_key`, timing-safe).
6. IP / CIDR allow-list.
7. Pre-issued bypass cookie (compared with `hash_equals` against the random `cookie_value`).
8. Otherwise: `CheckResult::denied()` + `maintenance.access_denied` event.

Steps 4 and 5 also attach an auto-cookie to the result (`CheckResult::setCookie`) when `$config->autoIssueBypassCookie` is true; the controller materialises it on the response.

### Backwards-compatibility contract

Three classes are intentional thin facades — **do not delete or refactor their public API without bumping the major version**:

| Facade | Wraps | Reason |
| --- | --- | --- |
| `Libraries\MaintenanceStorage` | `Storage\StorageFactory` + drivers | Existing apps do `new MaintenanceStorage($config)` |
| `Libraries\IpUtils` | `Libraries\IpChecker` | Old static API (`IpUtils::checkIp(...)`) |
| `Controllers\Maintenance::check()` | `Services\MaintenanceService::check()` | Static entry point used by `Filters\Maintenance` and tests |

Likewise `Config\Maintenance::$useCache` is `@deprecated` but still honoured by `StorageFactory` when `$driver` is null.

## Testing notes

- `tests/_support/TestCase` resets services on `setUp` and configures the `array` Settings handler. Always extend it.
- **Setting `$_GET[...]` AFTER calling `Services::request()` does not propagate** — the request is a singleton and reads globals at construction. Use the `mockRequest()` pattern from `tests/Maintenance/{ControllerTest,SecurityTest,Sprint3FeaturesTest}.php`:
  ```php
  $request = $this->createMock(IncomingRequest::class);
  $request->method('getGet')->willReturnCallback(...);
  $request->method('getCookie')->willReturnCallback(...);
  $request->method('getIPAddress')->willReturn('203.0.113.99');
  $request->method('getUri')->willReturn(new URI('http://localhost/path'));
  Services::injectMock('request', $request);
  ```
- `$config->driver` is honoured first; only set `useCache` when explicitly testing the legacy fallback path.
- Tests share `WRITEPATH/maintenance/` between runs. New test classes that touch storage should clear both file AND cache backends in `setUp` (see `SecurityTest::nukeAllStorage()`).

## Tooling quirks

- **PHPStan, Psalm, Rector all need to stay green** for `composer ci` to pass — they are wired as separate GitHub Actions workflows (`phpstan.yml`, `psalm.yml`, `rector.yml`).
- `phpstan.neon.dist` deliberately ignores deprecated-property accesses in `StorageFactory`, `MaintenanceStorage`, and `Migrate.php` — those are the BC bridge.
- `psalm.xml.dist` suppresses `MissingOverrideAttribute` because the package targets PHP 8.2 and `#[\Override]` is 8.3+.
- `rector.php` skips `Libraries/IpUtils.php` (BC wrapper) and `Commands/Publish.php` (legacy, out of scope).
- **CodeQL workflow only scans `actions`** (no PHP support upstream, no JS in repo). Don't add `javascript-typescript` back unless real JS is introduced — the run fails with exit code 32 otherwise.
- Workflow files live at `.github/workflows/{phpunit,code-style,phpstan,psalm,rector,codeql}.yml`. They share a master/main trigger fix that was missing in the original repo (the old `main.yml` triggered only on `main`, so CI never fired on a `master`-default repo).
- On Windows, git warns `LF will be replaced by CRLF` — that is expected. PHP-CS-Fixer's `line_ending` rule normalises to LF on commit; if `composer cs` complains about line endings only, run `composer cs-fix`.

## Documentation

User-facing documentation lives in `docs/` (not in `README.md` — the README is the landing page). Keep the two in sync:

- New CLI flag → update `docs/commands.md` + add or extend a recipe under `docs/examples/`.
- New `Config\Maintenance` property → update `docs/configuration.md`.
- New event → update `docs/filters-and-events.md`.
- Behavioural change → entry under `## [Unreleased]` in `CHANGELOG.md`.
- Roadmap items in `docs/roadmap.md` mark "still planned" features (multi-tenant isolation, trusted-proxy presets, DatabaseStorage driver, i18n, status dashboard, rate-limiting). Move them out of "still planned" when implementing.
