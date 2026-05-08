# Bypass methods

While maintenance is active, a request is denied with HTTP 503 unless one of
four bypass methods matches. They are evaluated in the order below — the first
match wins.

## Order of evaluation

1. **Config secret** — `?maintenance_secret=<key>` matched against
   `Config\Maintenance::$secretBypassKey`. Only checked when
   `$allowSecretBypass = true` AND `$secretBypassKey !== ''`.
2. **Window secret** — `?maintenance_secret=<key>` matched against the
   `secret_key` saved by `mm:down --secret`. Only checked when the maintenance
   data has `secret_bypass = true`.
3. **IP / CIDR** — the request's IP (`$request->getIPAddress()`) matched against
   the saved `allowed_ips` list. Supports IPv4, IPv6, and CIDR ranges in both.
4. **Cookie** — the cookie named by `cookie_name` whose value matches the
   stored 32-byte random `cookie_value`.

If none of the above matches, the request is denied. A
`maintenance.access_denied` event is emitted with the client IP and full data.

## Security guarantees

- **Timing-safe comparisons.** All secret and cookie comparisons go through
  `hash_equals()`. The package additionally guards against empty input
  matching an empty configured key.
- **High-entropy cookie value.** Every `mm:down` generates a fresh
  `bin2hex(random_bytes(32))` (64 hex chars). The legacy "cookie value equals
  cookie name" pattern is gone.
- **CIDR validation.** `mm:down --ip` rejects ranges with out-of-bounds prefix
  lengths (e.g. `10.0.0.0/40`, `2001:db8::/200`).

## Method-by-method

### IP allow-list

Everything that matters lives in [`IpChecker`](../src/Libraries/IpChecker.php).
Highlights:

- IPv4: literal, `/0`, `/8`, `/24`, `/32`.
- IPv6: literal, `/0`, ..., `/128`.
- Multiple entries per maintenance window (`mm:down --ip "10.0.0.0/8 192.168.1.5"`).
- Bounded internal cache (256 entries) — safe to use in long-running CLI
  workers.

### Secret URL

Two flavours:

- **Config secret**: a long-lived value pinned in `app/Config/Maintenance.php`.
  Useful for ops teams who always know "the" bypass key.
- **Window secret**: regenerated for each `mm:down` invocation. Useful when
  you want the secret to expire when the window ends.

Both are activated by the same query string: `?maintenance_secret=<value>`.
The package returns 200 if it matches; otherwise 503.

> **Tip.** Wire your status page to display the URL with the window secret to
> oncall engineers.

### Cookie

Issued by `mm:down`:

```
Cookie bypass: set cookie 'WAh0z8x3' with value '44746b03e657...e1'
```

Have your reverse proxy (or a small admin endpoint) set that cookie on
verified maintainer browsers. The cookie's name and value live in the
maintenance data, never on the URL.

Sprint 3 (planned) will set this cookie automatically when the secret URL is
visited, so a single `?maintenance_secret=…` issue lets the rest of that
session work without dragging the secret around in URLs.

## Testing

Setting `$_GET` after the request singleton has been resolved does **not**
update the cached request — see `tests/Maintenance/SecurityTest.php` for the
correct pattern using `Services::injectMock('request', $mock)`.
