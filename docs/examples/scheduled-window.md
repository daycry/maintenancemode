# Example: scheduled maintenance window

✅ Available since v3.0.

## Goal

Pre-schedule a maintenance window that activates and deactivates itself
automatically:

```bash
php spark mm:down \
  --message "Database migration" \
  --start "2026-05-10 02:00" \
  --end   "2026-05-10 04:00"
```

The flags accept any value `strtotime()` understands:

```bash
php spark mm:down --start "+10 minutes" --end "+1 hour"
php spark mm:down --start "2026-05-10T02:00:00Z" --end "2026-05-10T04:00:00Z"
```

## How it works

- **Before `--start`** the filter returns `CheckResult::pending()` and lets
  the request through (the application stays live).
- **Between `--start` and `--end`** the request goes through the normal
  bypass evaluation — IP / secret / cookie or 503.
- **After `--end`** the next request that hits the filter triggers an
  automatic deactivation. The maintenance file/cache is removed and a
  `maintenance.scheduled.expired` event is emitted with the original
  `MaintenanceData` payload.

If you provide both flags, `mm:down` rejects the call when `--end` is not
strictly after `--start`.

## Listening to the auto-deactivation

```php
// app/Config/Events.php
use CodeIgniter\Events\Events;

Events::on('maintenance.scheduled.expired', function (array $payload) {
    $message = $payload['data']->message;
    log_message('notice', "Scheduled maintenance ended automatically: {$message}");
});
```

## Caveats

- The auto-deactivation fires on the next *request* — not on a timer.
  Maintenance keeps blocking traffic until something hits the filter past
  the end timestamp. For a hard deadline, schedule a cron job to call
  `php spark mm:up` at the same time (or use a `maintenance.scheduled.expired`
  listener to hit a healthcheck after the window ends).
- All times are stored as Unix timestamps in the maintenance data, so the
  server's timezone determines how `strtotime()` interprets local strings.
  Pass ISO-8601 with an explicit offset (`2026-05-10T02:00:00+02:00`) to
  remove ambiguity.
