# Roadmap

The audit that drove the v3 refactor identified four sprint-sized chunks of
work. All four are now landed in v3.

## Done in v3

- ✅ **Sprint 1** — Security & bug fixes (timing-safe comparisons, working
  cookie bypass, atomic writes, CIDR validation, CI workflow trigger fix).
- ✅ **Sprint 2** — Architecture (DTO, storage drivers, `IpChecker`,
  `MaintenanceService`, helper, events, CLI options trait).
- ✅ **Sprint 3** — Feature parity & DX:
  - Scheduled windows (`--start` / `--end`) with auto-deactivation.
  - JSON response negotiation (`Accept: application/json` or `$jsonRoutes`).
  - Bypass routes whitelist (healthchecks, webhooks).
  - Auto-issued bypass cookie on successful secret URL bypass.
  - Per-environment templates (`$templateByEnv`).
  - `mm:preview` command for designing custom 503 views.
  - `--render` window-scoped template override (Laravel parity).
  - `--redirect` 302 alternative to 503 (Laravel parity).
- ✅ **Sprint 4** — Tooling & docs (PHPStan, modern CI matrix, Dependabot,
  `CHANGELOG`, `CONTRIBUTING`, `CODE_OF_CONDUCT`, `SECURITY`, `docs/` tree).

## Still planned

These are deferred from the original Sprint 3 plan or surfaced after v3.

| Feature | Status | Notes |
| --- | --- | --- |
| Trusted proxies / Cloudflare presets | planned | Built-in IP-range presets so IP bypass works out of the box behind a CDN. Today: configure `Config\App::$proxyIPs` manually. |
| Multi-tenant isolation | planned | Per-tenant cache key resolved via a closure or service. Today: override `$cacheKey` dynamically in `Config\Maintenance::__construct()`. |
| First-party `DatabaseStorage` driver | planned | The `StorageInterface` is already in place; just need the implementation. |
| i18n maintenance message | planned | `lang:Maintenance.scheduled` resolved at render time. |
| Status dashboard endpoint | planned | Admin-only HTML page summarising state without needing CLI access. |
| Rate-limiting on bypass-secret attempts | planned | Mitigate brute-force on `?maintenance_secret=…`. |
| Email / built-in webhook notifications | planned | The events bus is already there ([webhook-notifications example](examples/webhook-notifications.md)); a config-driven shortcut would save a few lines. |

## Influencing this list

- File a feature request: see [feature_request template](https://github.com/daycry/maintenancemode/issues/new?template=feature_request.yml).
- Open a discussion if you want to debate priorities before opening an issue.
- Pull requests welcome — see [CONTRIBUTING.md](../CONTRIBUTING.md).
