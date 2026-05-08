# Example: Slack / PagerDuty notifications

This one works **today** in v3.0 — the events are already wired.

## Slack on activate / deactivate

In `app/Config/Events.php`:

```php
use CodeIgniter\Events\Events;

$slackUrl = env('SLACK_OPS_WEBHOOK');

Events::on('maintenance.activated', function (array $payload) use ($slackUrl) {
    $message = $payload['data']['message'] ?? 'Maintenance';

    service('curlrequest')->post($slackUrl, [
        'json' => ['text' => "🔧 Maintenance ON — {$message}"],
    ]);
});

Events::on('maintenance.deactivated', function () use ($slackUrl) {
    service('curlrequest')->post($slackUrl, [
        'json' => ['text' => '✅ Maintenance OFF — site is live'],
    ]);
});
```

## PagerDuty on access denied (audit trail)

```php
Events::on('maintenance.access_denied', function (array $payload) {
    if (rand(1, 50) !== 1) {
        return; // sample 1/50 to avoid alert fatigue
    }

    log_message('notice', 'Maintenance blocked IP=' . $payload['ip']);
});
```

## Audit DB table

```php
Events::on('maintenance.bypassed', function (array $payload) {
    db_connect()->table('maintenance_audit')->insert([
        'method'    => $payload['method'],
        'ip'        => $payload['ip'],
        'happened_at' => date('Y-m-d H:i:s'),
    ]);
});
```

## Tip: don't block

These listeners run synchronously inside the request. Outbound HTTP calls in
`maintenance.access_denied` will slow down your 503 page. For anything heavier
than a log line, push to a queue:

```php
Events::on('maintenance.access_denied', function (array $payload) {
    queue('default')->push(NotifyDeniedJob::class, $payload);
});
```
