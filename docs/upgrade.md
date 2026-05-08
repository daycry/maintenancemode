# Upgrade guide

## v2.x → v3.x

### Highlights

- **No breaking API changes** for existing callers. `MaintenanceController::check()`,
  `Filters\Maintenance`, `Libraries\MaintenanceStorage`, and the `mm:*` commands
  keep their public signatures.
- New layered architecture with `MaintenanceService`, `Storage\*`, `IpChecker`,
  and the `MaintenanceData` DTO. See [architecture.md](architecture.md).
- Config-level deprecations (still honoured, will be removed in v4).

### What you should change

| Before | After | Why |
| --- | --- | --- |
| `$config->useCache = true;` | `$config->driver = 'cache';` | Explicit driver picker, future-proof. |
| `IpUtils::checkIp(...)` | `(new IpChecker())->checkIp(...)` | Testable, bounded cache. |
| Setting cookie value to the cookie's name | Reissue with `mm:up && mm:down`; use the printed `cookie_value` | Cookie bypass now requires the high-entropy random value. |

### What was actually broken

If you upgrade from v2.x:

1. **CI was not running** if your default branch was `master`. The workflow
   triggered only on `main`. Re-tag and re-run after merging to ensure CI
   actually exercises your branch.
2. **Cookie bypass was effectively unusable**. Before v3 it required the
   cookie's *value* to equal its *name*, which is undocumented and almost
   certainly never worked for anyone in practice. After v3 the bypass uses a
   32-byte random value generated at `mm:down` time. Operators who relied on
   cookie bypass will need to re-issue cookies after upgrading.
3. **Secret bypass was timing-attackable** (`===` comparison). Now uses
   `hash_equals()` with a non-empty guard. No action needed; this is a
   silent improvement.
4. **File-backed storage could corrupt under concurrent writes.** Now atomic
   via `flock()`. No action needed.

### Things you might notice

- `composer require daycry/maintenancemode` now also autoloads the global
  `maintenance()` helper. If you have a function called `maintenance()` in
  your project already, rename one of them.
- `Config\Maintenance` has a new property `?string $driver`. Nothing breaks if
  you leave it `null`, but you'll get a `@deprecated` notice on `useCache` if
  PHPStan or Psalm runs over your config.
- `mm:status` no longer issues an outbound HTTP request to detect your public
  IP unless you pass `--show-public-ip`. Old behaviour was: blocking call with
  no timeout, swallowed errors.

### Database / multi-tenant?

Sprint 3 (planned) brings JSON responses, scheduled windows, multi-tenant
isolation, and a database driver. They're additive — v3.0 won't have them
yet, v3.1+ will.

## v1.x → v2.x

See git history. v2 introduced the cache driver and the legacy `$useCache`
flag. v3 deprecates that flag in favour of `$driver`.
