# Example: JSON 503 for APIs

✅ Available since v3.0.

## Goal

When the request advertises `Accept: application/json` (or the URL matches
`Config\Maintenance::$jsonRoutes`), respond with a JSON 503 instead of HTML:

```http
HTTP/1.1 503 Service Unavailable
Retry-After: 3600
Content-Type: application/json

{
  "status": 503,
  "error": "Service Unavailable",
  "message": "Upgrading the database",
  "retry_after": 3600,
  "estimated_end": "2026-05-10T04:00:00+00:00"
}
```

`estimated_end` is included only when a duration or scheduled end is set.

## Configuration

```php
// app/Config/Maintenance.php
public array $jsonRoutes = [
    '/api/*',
    '/v2/*',
];
```

Combined with the `Accept` header, the rules are:

- Request sends `Accept: application/json` (case-insensitive) → JSON.
- Request URI matches a `$jsonRoutes` pattern → JSON.
- Otherwise → HTML 503 (the default behaviour).

Pattern syntax: literal paths or trailing-`*` prefixes. `/health` matches
exactly, `/api/*` matches anything under `/api/`. No regex.

## Try it

```bash
php spark mm:down --message "Upgrading"

# HTML 503 (browser default)
curl -i http://localhost:8080/dashboard
# → HTTP/1.1 503 Service Unavailable
# → Content-Type: text/html

# JSON 503 (Accept header)
curl -i -H 'Accept: application/json' http://localhost:8080/dashboard
# → HTTP/1.1 503 Service Unavailable
# → Content-Type: application/json
# → {"status":503,"error":"Service Unavailable",...}

# JSON 503 (route pattern, no header needed)
curl -i http://localhost:8080/api/users
# → HTTP/1.1 503 Service Unavailable
# → Content-Type: application/json
```

## Customising the JSON shape

The default body is built in
`Daycry\Maintenance\Controllers\Maintenance::buildJsonResponse()`. To
customise it, listen to `maintenance.access_denied` and short-circuit with
your own response, or extend the controller:

```php
// app/Controllers/Maintenance.php
namespace App\Controllers;

class Maintenance extends \Daycry\Maintenance\Controllers\Maintenance
{
    public static function check()
    {
        // ... your custom logic, then call parent or build your own response
        return parent::check();
    }
}
```

Then point the filter at your subclass via `app/Config/Filters.php`.
