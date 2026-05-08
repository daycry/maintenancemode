# Example: per-tenant maintenance *(planned — Sprint 3)*

> ⚠️ This feature is on the [roadmap](../roadmap.md) but not yet implemented.

## Goal

In a SaaS app where one CodeIgniter instance serves many tenants, allow each
tenant to be in or out of maintenance independently:

```php
$config->driver           = 'cache';
$config->tenantResolver   = static fn () => current_tenant_id();
$config->cacheKeyTemplate = 'maintenance_mode_data_{tenant}';
```

`MaintenanceService` would then look up `maintenance_mode_data_acme`,
`maintenance_mode_data_globex`, etc. — one entry per tenant.

## Workaround today

Set `$cacheKey` dynamically in `app/Config/Maintenance.php`'s constructor:

```php
public function __construct()
{
    parent::__construct();

    $tenant = service('tenants')->current()->slug ?? 'default';
    $this->cacheKey = "maintenance_mode_data_{$tenant}";
}
```

Caveat: this fires on every `config('Maintenance')` call. Cache the tenant
lookup if it's expensive.
