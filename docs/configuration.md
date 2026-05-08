# Configuration reference

After running `php spark mm:publish` you'll have a local copy at
`app/Config/Maintenance.php`. Every property is documented below.

## Storage

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `$driver` | `?string` | `null` | `'cache'` or `'file'`. When set, takes precedence over `$useCache`. Recommended way to choose the backend. |
| `$useCache` | `bool` | `true` | **Deprecated.** Legacy fallback. `true` ≡ `$driver = 'cache'`, `false` ≡ `$driver = 'file'`. |
| `$cacheKey` | `string` | `'maintenance_mode_data'` | Key used in the cache backend. Change it if you have multiple instances sharing one cache. |
| `$cacheTTL` | `int` | `0` | TTL in seconds. `0` = no expiration (the entry is removed by `mm:up`). |
| `$cacheHandler` | `?string` | `null` | Force a specific CI cache handler (`redis`, `memcached`, etc.). `null` uses the framework default. |
| `$filePath` | `string` | `WRITEPATH . 'maintenance/'` | Where to put the JSON file when using the `file` driver. |
| `$fileName` | `string` | `'down'` | File name (no extension) inside `$filePath`. |

> See [storage-drivers.md](storage-drivers.md) for trade-offs and migration notes.

## Behaviour

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `$enableLogging` | `bool` | `true` | Write info/error log entries on activate, deactivate, bypass, denied. |
| `$defaultMessage` | `string` | `'We are currently performing scheduled maintenance...'` | Used when `mm:down` is run without `--message` or when the saved message is empty. |
| `$showEstimatedTime` | `bool` | `false` | Render the ETA on the 503 page. |
| `$defaultDurationMinutes` | `int` | `60` | Default ETA when `mm:down` is run without `--duration`. |
| `$customTemplate` | `string` | `''` | View name to render instead of the bundled 503 template (e.g. `'errors/html/my_503'`). |
| `$retryAfterSeconds` | `int` | `3600` | Value sent in the `Retry-After` HTTP header (used by load balancers, bots, browsers for backoff). |

## Bypass — config-level secret URL

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `$allowSecretBypass` | `bool` | `false` | Master switch for the URL bypass (`?maintenance_secret=...`) at config level. |
| `$secretBypassKey` | `string` | `''` | The secret. Compared with `hash_equals()`. **Empty key disables the bypass even when the switch is on.** |
| `$autoIssueBypassCookie` | `bool` | `true` | When a secret URL bypass succeeds, also set the bypass cookie so the rest of the session works without dragging the secret around. |
| `$bypassCookieLifetime` | `int` | `86400` | Lifetime in seconds for the auto-issued bypass cookie. `0` = session cookie. |

> Per-window secrets (`mm:down --secret …`) work independently of these
> settings; see [bypass.md](bypass.md).

## Routing

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `$bypassRoutes` | `list<string>` | `[]` | Route paths the filter NEVER blocks (healthchecks, webhooks). Trailing `*` wildcards supported (e.g. `'/api/webhooks/*'`). |
| `$jsonRoutes` | `list<string>` | `[]` | Routes that should always receive a JSON 503 instead of HTML. Trailing `*` wildcards supported. The `Accept: application/json` request header also forces JSON regardless of this list. |

> See [examples/api-json-response.md](examples/api-json-response.md) and the
> bypass-route section of [bypass.md](bypass.md).

## Templates

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `$customTemplate` | `string` | `''` | View name to render instead of the bundled 503 template. |
| `$templateByEnv` | `array<string,string>` | `[]` | Per-environment override, keyed by `ENVIRONMENT` value. Falls back to `$customTemplate` then to the bundled default. |

Per-window override: `mm:down --render <view>` wins over both of the above
for that maintenance window only.

## The `maintenance()` helper

Once installed, the package autoloads `src/Helpers/maintenance_helper.php`,
which exposes a single function:

```php
maintenance(): \Daycry\Maintenance\Services\MaintenanceService
```

Use it anywhere:

```php
if (maintenance()->isActive()) {
    // bypass long-running cron jobs while we're under maintenance
    return;
}

$data = maintenance()->getData(); // ?MaintenanceData
```

The service is rebuilt from `config('Maintenance')` on every call, so injected
mocks during tests are always honoured.

## Programmatic config (tests)

Inject a custom config in tests via CodeIgniter's standard `Factories`:

```php
use CodeIgniter\Config\Factories;
use Daycry\Maintenance\Config\Maintenance;

$config             = new Maintenance();
$config->driver     = 'file';
$config->filePath   = WRITEPATH . 'maintenance/';
$config->enableLogging = false;

Factories::injectMock('config', 'Maintenance', $config);
```
