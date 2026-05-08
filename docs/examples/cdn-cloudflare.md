# Example: real client IP behind a CDN *(planned — Sprint 3)*

> ⚠️ Sprint 3 will add first-class trusted-proxy / Cloudflare presets to this
> package. Until then, configure CodeIgniter itself.

## The problem

Behind Cloudflare/AWS ALB/NGINX, `$_SERVER['REMOTE_ADDR']` is the proxy's IP,
not the user's. IP-based bypass will never match unless you tell the
framework which proxies to trust and which header carries the real client IP.

## CodeIgniter setup

In `app/Config/App.php`:

```php
public array $proxyIPs = [
    // Cloudflare ranges (https://www.cloudflare.com/ips/)
    '173.245.48.0/20',
    '103.21.244.0/22',
    // ... full list
    '2400:cb00::/32',
    // ...
];

public string $proxyIPHeader = 'CF-Connecting-IP';
```

For AWS ALB:

```php
public string $proxyIPHeader = 'X-Forwarded-For';
```

Once configured, `$request->getIPAddress()` (which `MaintenanceService` uses)
returns the real client IP. The IP allow-list works as expected.

## Diagnosis

If you're not sure what the framework sees, add a debug listener:

```php
Events::on('maintenance.access_denied', function (array $payload) {
    log_message('debug', '503 from IP ' . $payload['ip']);
});
```

Compare with the real client IP in your CDN access logs.

## Sprint 3 plan

The package will ship preset proxy lists (`'cloudflare'`, `'aws-alb'`,
`'fastly'`) plus dynamic Cloudflare range refresh, so you don't have to hard-code
hundreds of CIDR entries.
