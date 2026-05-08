# Installation

## Requirements

- PHP **8.2** or newer
- CodeIgniter **4.x**
- `codeigniter4/settings` ^2.2 (installed automatically)

## Install

```bash
composer require daycry/maintenancemode
```

Composer's `files` autoload registers the global [`maintenance()`](configuration.md#the-maintenance-helper)
helper, so you can call it from anywhere immediately after install.

## Publish the config

The package ships sensible defaults, but most users want to publish a local
copy of `Config\Maintenance` so they can pin secrets and choose a driver:

```bash
php spark mm:publish
```

This creates `app/Config/Maintenance.php` (and the package's 503 view skeleton
under `app/Views/errors/`). Edit those files freely — you own them.

## Verify

```bash
php spark mm:status
```

You should see something like:

```
✅ **** Application is LIVE ****
Users can access the application normally.

Storage method: Cache
```

If you see "MAINTENANCE MODE" instead, run `php spark mm:up` first to clear any
leftover state.

## Wire the filter

To make the package actually intercept HTTP requests, add the filter alias and
apply it to whatever routes you want guarded. In `app/Config/Filters.php`:

```php
public array $aliases = [
    // ...
    'maintenance' => \Daycry\Maintenance\Filters\Maintenance::class,
];

public array $globals = [
    'before' => [
        'maintenance', // applies to every request
    ],
    'after' => [],
];
```

If you'd rather guard a subset of routes, use route-level filters in
`app/Config/Routes.php` instead of `$globals`.

## Next steps

- [Try the happy path](examples/basic-maintenance.md)
- [Read the full config reference](configuration.md)
- [Understand the bypass priority](bypass.md)
