# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added — Sprint 3 (feature parity with Laravel `artisan down`)
- **Scheduled maintenance windows** via `mm:down --start "..." --end "..."`.
  Before `--start` the filter lets traffic through; after `--end` it
  auto-deactivates on the next request and emits a
  `maintenance.scheduled.expired` event. Accepts anything `strtotime()`
  understands.
- **JSON response negotiation**. When the request sends
  `Accept: application/json` or its URI matches `Config\Maintenance::$jsonRoutes`,
  the package returns a JSON 503 with `status`, `error`, `message`,
  `retry_after`, and (when known) `estimated_end`.
- **Bypass routes whitelist** (`Config\Maintenance::$bypassRoutes`). Listed
  paths (with optional trailing `*`) skip the maintenance check entirely —
  ideal for `/health`, `/api/webhooks/*` etc.
- **Auto secret-cookie bypass.** On a successful URL secret bypass, the
  package now sets the bypass cookie (`HttpOnly`, `SameSite=Lax`, `Secure`
  on HTTPS) so the rest of the session works without dragging the secret
  through every URL. Configurable via `$autoIssueBypassCookie` and
  `$bypassCookieLifetime`.
- **Per-environment templates** (`Config\Maintenance::$templateByEnv`). Map
  `ENVIRONMENT` values to specific 503 view names; falls back to
  `$customTemplate`, then to the bundled default.
- **`mm:preview` command** to render the 503 view with stub data without
  activating maintenance. `--message`, `--template`, `--output`.
- **`mm:down --render <view>`** — Laravel parity. Per-window template override
  that wins over `$templateByEnv` and `$customTemplate`.
- **`mm:down --redirect <url>`** — Laravel parity. When set, denied requests
  receive a 302 to the configured URL instead of a 503.
- New `tests/Maintenance/Sprint3FeaturesTest.php` (17 cases).

### Added
- **Storage driver pattern** (`StorageInterface`, `FileStorage`, `CacheStorage`, `StorageFactory`).
  Pick the backend explicitly via `Config\Maintenance::$driver` (`'cache'` or `'file'`).
- **`MaintenanceService`** centralises the bypass evaluation that used to live in
  `Controllers\Maintenance::check()` and `Commands\Status` (~200 LOC of duplication).
- **`MaintenanceData` DTO** replaces the loose `(object) json_decode` shape with
  a typed, immutable value object (`fromArray`, `fromJson`, `toArray`).
- **`IpChecker`** — instantiable, testable replacement for the old static
  `IpUtils`. Cache is now bounded (LRU-like, 256 entries) so long-running CLI
  workers can't leak memory.
- **`maintenance()` helper** (`src/Helpers/maintenance_helper.php`) — quick
  access to the service from anywhere: `if (maintenance()->isActive()) { ... }`.
- **Trait `ParsesCliOptions`** — eliminates the `$params['x'] ?? CLI::getOption('x')`
  boilerplate from spark commands.
- **Framework events**: `maintenance.activated`, `maintenance.deactivated`,
  `maintenance.bypassed`, `maintenance.access_denied`. Wire your own listeners
  to push to Slack, PagerDuty, etc.
- New `tests/Maintenance/SecurityTest.php` (12 cases) and
  `tests/Maintenance/IpCheckerTest.php` (16 cases).
- `phpstan.neon.dist` at level 6 with PHPStan running in CI.
- CI now also runs PHP-CS-Fixer and PHPStan as separate jobs, on PHP 8.2 / 8.3 / 8.4
  plus a `--prefer-lowest` matrix entry.
- `.github/dependabot.yml` for weekly Composer + GitHub Actions updates.

### Changed
- **Cookie bypass** now requires a high-entropy `cookie_value` (32 random bytes,
  hex-encoded) compared with `hash_equals()`. Previously the comparison was
  effectively broken — it required the cookie's *value* to equal its *name*.
- **Secret bypass** comparisons (config and per-window) are now timing-safe
  (`hash_equals()` with non-empty guard). Closes a timing-attack surface and
  prevents an empty config key from accidentally accepting empty input.
- `MaintenanceStorage::save()` now writes atomically with `flock(LOCK_EX)` and
  encodes/decodes JSON with `JSON_THROW_ON_ERROR`. Race conditions between
  concurrent workers can no longer corrupt the file.
- `Status::getCurrentClientIP()` no longer issues a blocking outbound HTTP
  request implicitly. The `--show-public-ip` flag enables the lookup with a
  3 s timeout via `Services::curlrequest`.
- `Commands\Down`'s CIDR validation now checks the prefix range (0–32 IPv4 /
  0–128 IPv6) in addition to `is_numeric`.
- `Controllers\Maintenance::check()` is now a thin shim that delegates to
  `MaintenanceService`. Behavior is identical for existing callers.
- `Libraries\MaintenanceStorage` is now a backwards-compatible facade over the
  new storage drivers. Existing `new MaintenanceStorage($config)` callers keep
  working unchanged.
- `Libraries\IpUtils` is now a thin BC wrapper that delegates to `IpChecker`.
  Marked `@deprecated`.

### Fixed
- **CI workflow now triggers on `master`**. Previously it only fired on `main`,
  so no push or PR had run CI since the merge to `master`.
- `BypassTest::testSecretBypassBasicFunctionality` was silently failing in
  isolation (not exercising what its name implied) because it mutated `$_GET`
  after `Services::request()` had cached its globals. Refactored to inject a
  request mock, which is the pattern the rest of `ControllerTest` uses.

### Deprecated
- `Config\Maintenance::$useCache` — set `$driver = 'cache'` or `'file'` instead.
  The flag is still honoured for back-compat.
- `Libraries\IpUtils` — use `Libraries\IpChecker`.

## [2.x] — previous releases

See git history. This package was previously published with brief release notes
in `README.md`; the Unreleased section above is the first formal changelog.

[Unreleased]: https://github.com/daycry/maintenancemode/compare/v2.1.0...HEAD
