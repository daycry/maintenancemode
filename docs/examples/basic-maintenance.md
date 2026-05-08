# Example: basic maintenance

The shortest end-to-end story. Two terminals, one browser.

## 1. Set up

```bash
composer require daycry/maintenancemode
php spark mm:publish
```

In `app/Config/Filters.php`:

```php
public array $aliases = [
    'maintenance' => \Daycry\Maintenance\Filters\Maintenance::class,
];

public array $globals = [
    'before' => ['maintenance'],
];
```

## 2. Activate maintenance

```bash
php spark mm:down --message "Upgrading the database, back in 30 minutes" --duration 30
```

Output:

```
**** Application is now in MAINTENANCE MODE ****
Cookie bypass: set cookie 'WAh0z8x3' with value '44746b03e6...e1'

🔧 Application is in MAINTENANCE MODE
Storage method: Cache
🔍 Current Bypass Status:
   🌐 IP Address bypass configured (current IP 0.0.0.0 not in allowed list)
   🍪 Cookie bypass configured (cookie not set or invalid)
🚦 Access Status from CLI:
   ✅ Access ALLOWED: CLI access (always allowed)
```

## 3. Hit the site as a normal user

```bash
curl -i http://localhost:8080/
HTTP/1.1 503 Service Unavailable
Retry-After: 3600
Content-Type: text/html; charset=UTF-8

<!DOCTYPE html>
<title>Service Unavailable</title>
... message: "Upgrading the database, back in 30 minutes"
```

## 4. Bypass as an operator

Three options, pick whichever fits.

### A. By IP

```bash
# Activate with your IP allow-listed:
php spark mm:up
php spark mm:down --message "..." --ip "203.0.113.5 10.0.0.0/8"
```

### B. By secret URL

```bash
php spark mm:up
php spark mm:down --message "..." --secret "supersecret"
# Then visit:
curl -i 'http://localhost:8080/?maintenance_secret=supersecret'
HTTP/1.1 200 OK
```

### C. By cookie

```bash
php spark mm:down --message "..."
# Output mentions:  Cookie bypass: set cookie 'WAh0z8x3' with value '<HEX>'

curl -i -b "WAh0z8x3=<HEX>" http://localhost:8080/
HTTP/1.1 200 OK
```

## 5. Check status from anywhere

```bash
php spark mm:status
```

The `Access Status from CLI` block walks the bypass evaluation step by step
and tells you exactly why the current session is allowed or blocked.

## 6. Deactivate

```bash
php spark mm:up
```

```
**** Application is now LIVE! ****
Users can now access the application normally.
```

The `maintenance.deactivated` event is emitted — wire a listener to clear
edge caches, ping a webhook, or whatever post-maintenance tasks you have.
