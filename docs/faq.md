# FAQ

### Does it work in CLI?

The filter no-ops in CLI runs (CodeIgniter sets `is_cli() === true` for spark
commands and queue workers). When `ENVIRONMENT === 'testing'` it does run,
because the test suite needs to exercise the bypass path.

If you want a queue worker to *honour* maintenance, call the helper
explicitly:

```php
if (maintenance()->isActive()) {
    return; // skip this run
}
```

### Can I have different maintenance windows per route?

Yes — apply the filter only to the routes you want to guard. Use the alias in
route definitions instead of `$globals`:

```php
$routes->group('admin', ['filter' => 'maintenance'], function ($routes) {
    // ...
});
```

`/health`, webhook callbacks, and similar should usually be left out of the
filter.

### Multi-tenant?

Not yet. It's on the [roadmap](roadmap.md). For now, work around it by:

- Running multiple CodeIgniter apps with separate caches, OR
- Using `$cacheKey` per tenant — set it dynamically in
  `app/Config/Maintenance.php`'s constructor.

### Why JSON?

The bundled 503 view is HTML. JSON content negotiation (`Accept:
application/json`) is also on the roadmap. In the meantime, override
`$customTemplate` with a view that emits JSON, or wrap the filter and inspect
the request yourself.

### Does it require Redis?

No. The `cache` driver uses CI4's cache abstraction, which works with file,
APCu, Memcached, Redis, etc. The `file` driver writes directly to
`WRITEPATH/maintenance/`.

### Can I migrate from `useCache=false` to `useCache=true` without losing the active window?

Yes — that's exactly what `php spark mm:migrate` does. See
[storage-drivers.md](storage-drivers.md#switching-drivers-on-a-live-system).

### How do I customise the 503 page?

Either:

- Set `Config\Maintenance::$customTemplate = 'errors/html/my_503'`, or
- `php spark mm:publish` and edit `app/Views/errors/html/error_503.php`.

Variables passed to the template: `$message`, `$data` (the `MaintenanceData`
DTO), `$config` (the `Config\Maintenance` instance).

### Does this protect against DDoS / bad bots?

No. The 503 still costs CPU/RAM to render. Park your DDoS protection at the
CDN / load-balancer layer.

### What's `Retry-After`?

An HTTP header that tells well-behaved clients (CDNs, search engine crawlers,
browsers' fetch retries) how long to wait before retrying. Configure it via
`Config\Maintenance::$retryAfterSeconds`. Sprint 3 will allow an HTTP-date
value too, useful when you have a known maintenance end time.

### My tests pass but the secret bypass doesn't work in production!

Different cause from the test issue. In production, query parameters arrive
with the request. Check that `$config->allowSecretBypass = true` AND that the
client actually hits the URL with `?maintenance_secret=…`. Run
`php spark mm:status` from the same shell as your web server — its CLI
"Access Status" panel walks the bypass logic step by step.
