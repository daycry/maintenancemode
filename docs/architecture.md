# Architecture

The package is split into small, single-purpose classes. This page is the
reference map.

## High-level flow

```
HTTP request
    │
    ▼
┌──────────────────────────────┐
│ Daycry\Maintenance\Filters\  │
│ Maintenance (before filter)  │
└──────────────┬───────────────┘
               │
               ▼
┌──────────────────────────────┐
│ Controllers\Maintenance      │   CLI short-circuit lives here
│   ::check()                  │
└──────────────┬───────────────┘
               │
               ▼
┌──────────────────────────────┐
│ Services\MaintenanceService  │   single source of truth
│   ::check(IncomingRequest)   │
│      ↓                       │
│   evaluates bypass:          │
│     1. config secret URL     │
│     2. window secret URL     │
│     3. allowed IP / CIDR     │
│     4. bypass cookie         │
└─────┬────────────┬───────────┘
      │            │
      ▼            ▼
┌───────────┐  ┌──────────────┐
│ Storage   │  │ IpChecker    │
│ Interface │  │ (LRU cache)  │
└─────┬─────┘  └──────────────┘
      │
      ├── FileStorage (flock + JSON_THROW_ON_ERROR)
      └── CacheStorage (CI4 Cache: redis, memcached, file...)
```

## Key types

| Class | File | Responsibility |
| --- | --- | --- |
| `MaintenanceService` | `src/Services/MaintenanceService.php` | Orchestrates the bypass logic. Pure: returns a `CheckResult`, doesn't throw. |
| `CheckResult` | `src/Services/CheckResult.php` | Immutable outcome (`allowed`, `reason`). |
| `MaintenanceData` | `src/DTO/MaintenanceData.php` | Typed value object for the persisted window. |
| `StorageInterface` | `src/Storage/StorageInterface.php` | Contract for persistence. |
| `FileStorage` | `src/Storage/FileStorage.php` | JSON file backend with exclusive locks. |
| `CacheStorage` | `src/Storage/CacheStorage.php` | CI4 Cache backend. |
| `StorageFactory` | `src/Storage/StorageFactory.php` | Picks the right driver from config. |
| `IpChecker` | `src/Libraries/IpChecker.php` | IPv4/IPv6/CIDR matching with bounded cache. |

### Backwards-compatible shims

| Old API | What it does now |
| --- | --- |
| `Libraries\MaintenanceStorage` | Facade over `StorageFactory`. Existing `new MaintenanceStorage($config)` callers still work. |
| `Libraries\IpUtils` | Static wrapper that delegates to `IpChecker`. Marked `@deprecated`. |
| `Controllers\Maintenance::check()` | Thin shim that resolves the request, calls `MaintenanceService`, throws `ServiceUnavailableException` on denial. |

## Events

Wired by `MaintenanceService` (and by the `mm:down` / `mm:up` commands):

- `maintenance.activated` — payload: `['data' => array]`
- `maintenance.deactivated` — no payload
- `maintenance.bypassed` — payload: `['method' => string, 'ip' => string]`
- `maintenance.access_denied` — payload: `['ip' => string, 'data' => MaintenanceData]`

See [filters-and-events.md](filters-and-events.md) for listener examples.

## Why this shape?

Two pre-Sprint-2 problems drove the refactor:

1. **~200 lines of duplicated bypass logic** lived in
   `Controllers\Maintenance::check()` and `Commands\Status` — the CLI status
   command literally re-implemented the controller's checks. Extracting the
   service kills that duplication.
2. **`Libraries\IpUtils` was wholly excluded from coverage** (`@codeCoverageIgnore`)
   because its static methods + global cache made it untestable. `IpChecker`
   is instantiable, has a bounded cache, and ships with 16 unit tests.
