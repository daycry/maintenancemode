# Filter & events

The package exposes two integration points: a CodeIgniter filter that
intercepts HTTP requests and a small set of framework events you can listen to.

## The filter

`Daycry\Maintenance\Filters\Maintenance` is a `before` filter. Wire it as
described in [installation.md](installation.md#wire-the-filter):

```php
public array $aliases = [
    'maintenance' => \Daycry\Maintenance\Filters\Maintenance::class,
];

public array $globals = [
    'before' => ['maintenance'],
];
```

When maintenance is active and the request fails every bypass check, the
filter throws `Daycry\Maintenance\Exceptions\ServiceUnavailableException`,
which CodeIgniter renders as HTTP 503 using the bundled
`Views/errors/html/error_503.php` template (or your `$customTemplate`).

The filter sets the `Retry-After` HTTP header from
`Config\Maintenance::$retryAfterSeconds` so well-behaved load balancers and
crawlers back off appropriately.

## Events

The package emits these CodeIgniter events. Wire listeners in
`app/Config/Events.php`:

```php
use CodeIgniter\Events\Events;

Events::on('maintenance.activated', function (array $payload) {
    // $payload['data'] is the array passed to mm:down
    log_message('notice', 'Maintenance started: ' . $payload['data']['message']);
});

Events::on('maintenance.deactivated', function () {
    log_message('notice', 'Maintenance ended');
});

Events::on('maintenance.bypassed', function (array $payload) {
    // $payload['method'] is one of: config_secret, data_secret, ip, cookie
    // $payload['ip']     is the client IP that bypassed
});

Events::on('maintenance.access_denied', function (array $payload) {
    // $payload['ip']   client IP
    // $payload['data'] MaintenanceData DTO
});
```

### Event reference

| Event | Fired by | Payload |
| --- | --- | --- |
| `maintenance.activated` | `mm:down` (after a successful save) | `['data' => array]` — the maintenance data array |
| `maintenance.deactivated` | `mm:up` (after a successful remove) | none |
| `maintenance.bypassed` | `MaintenanceService::check()` on a successful bypass | `['method' => string, 'ip' => string]` |
| `maintenance.access_denied` | `MaintenanceService::check()` when no bypass matched | `['ip' => string, 'data' => MaintenanceData]` |

### Recipes

- **Slack/PagerDuty**: log to a webhook from the activated/deactivated listeners.
- **Audit trail**: write IP + method to a `maintenance_audit` DB table from
  `bypassed` and `access_denied` to investigate access patterns afterwards.
- **Cache warmup**: prime CDN/edge caches inside `maintenance.deactivated` so
  the first real request after maintenance hits a warm cache.

## Filter vs direct service call

The filter is the right tool for HTTP traffic. For other contexts (queue
workers, scheduled commands, etc.) prefer the helper:

```php
if (maintenance()->isActive()) {
    return; // skip cron run while we're under maintenance
}
```
