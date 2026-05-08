# Storage drivers

Maintenance state has to live somewhere. The package supports two backends out
of the box.

## Choosing a driver

Set `Config\Maintenance::$driver` to `'cache'` or `'file'`. (The legacy
`$useCache` boolean still works but is deprecated.)

|                 | `file` | `cache` |
| --- | --- | --- |
| Persists across cache flush | ✅ | ❌ (depends on backend TTL/eviction) |
| Distributed across nodes | ❌ (filesystem-local) | ✅ if the cache backend is shared (Redis, Memcached) |
| Atomic writes | ✅ (`flock(LOCK_EX)`) | ✅ (cache primitive) |
| Recommended for | local dev, single-server deployments | production, multi-node, autoscaled |

## Architecture

```
config('Maintenance')
        │
        ▼
StorageFactory::make($config)
        │
        ├── 'cache' → CacheStorage(CI4 Cache)
        └── 'file'  → FileStorage(filesystem + flock)

         implements StorageInterface
         { isActive, getData, save, remove, clearAll }
```

The drivers themselves are dumb — they just persist `MaintenanceData`. All
bypass evaluation, logging and event dispatching lives in
[`MaintenanceService`](architecture.md). That separation is what made the
code pass `composer ci` cleanly across PHP 8.2 / 8.3 / 8.4.

## Switching drivers on a live system

The safe path is `mm:migrate`:

```bash
# 1. Already in maintenance with $driver = 'file'.
# 2. Edit app/Config/Maintenance.php, set $driver = 'cache'.
# 3. Move the active state from the file into the cache:
php spark mm:migrate

# 4. Verify both backends agree:
php spark mm:status
```

`mm:migrate` reads the file backend, writes to the cache backend, then deletes
the file. If anything goes wrong it logs an error and leaves the file alone.

## Wiping everything

```bash
php spark mm:migrate --clear
```

Removes state from BOTH cache and file storage. Useful for tests or when
recovering from corruption.

## Implementing your own driver

`StorageInterface` is small (5 methods). Build a `DatabaseStorage`, a
`RedisStorage` (without going through CI4 Cache), or a multi-tenant variant by
implementing the interface. The factory currently dispatches on a fixed string;
extending it to load arbitrary classes by FQCN is a one-line change you can do
locally if you need it.

```php
final class DatabaseStorage implements StorageInterface
{
    public function isActive(): bool { /* SELECT 1 FROM maintenance ... */ }
    public function getData(): ?MaintenanceData { /* ... */ }
    public function save(MaintenanceData $data): bool { /* INSERT/UPDATE */ }
    public function remove(): bool { /* DELETE */ }
    public function clearAll(): bool { return $this->remove(); }
}
```

> A first-party `DatabaseStorage` is on the [roadmap](roadmap.md).
