# Security

This page expands on the threat model summarised in [SECURITY.md](../SECURITY.md).

## Goals

The package guards an HTTP endpoint while maintenance is active. Specifically:

1. The 503 response is reachable for unauthorised clients.
2. Maintainers (operators, internal teams, smoke-test bots) can bypass via a
   pre-arranged signal: a secret URL, a cookie, or a known IP.
3. None of those signals can be spoofed by external parties without prior
   knowledge.

## Threats handled

| Threat | Mitigation | Code |
| --- | --- | --- |
| **Timing attack on secret comparison** | All secret/cookie comparisons use `hash_equals()` and reject empty input even when the configured key is empty. | `Services\MaintenanceService::check()` |
| **Cookie forgery** | The cookie's value is a fresh `bin2hex(random_bytes(32))` per `mm:down`. Comparison is timing-safe. The legacy "cookie value equals cookie name" pattern is gone. | `Commands\Down`, `Services\MaintenanceService` |
| **Race conditions during writes** | File backend uses `flock(LOCK_EX)` + `JSON_THROW_ON_ERROR`. Multiple workers can no longer corrupt the file. | `Storage\FileStorage::writeExclusive()` |
| **Out-of-range CIDR injection** | `mm:down --ip` validates prefix lengths (0–32 IPv4 / 0–128 IPv6) on top of `is_numeric`. | `Commands\Down`, `Libraries\IpChecker::validateCidr()` |
| **Implicit outbound HTTP** | `mm:status` no longer issues `file_get_contents('https://api.ipify.org')` implicitly; the lookup is opt-in via `--show-public-ip` with strict timeouts. | `Commands\Status::fetchPublicIp()` |
| **Unbounded in-memory cache** | `IpChecker` keeps at most 256 entries (LRU-ish, FIFO eviction). | `Libraries\IpChecker` |

## Threats out of scope

We don't try to defend against:

- **Filesystem / cache compromise.** If an attacker can write to your
  `WRITEPATH/maintenance/` or your Redis, they can flip maintenance on or off
  arbitrarily.
- **Application secret leak.** If `secretBypassKey` ends up in a public commit,
  rotate it. Same for `cookie_value` (run `mm:up && mm:down` to reissue).
- **Trust at the proxy layer.** If a CDN or reverse proxy spoofs
  `X-Forwarded-For`, the IP allow-list will believe it. Configure trusted
  proxies in CodeIgniter (`Config\App::$proxyIPs`) before relying on IP-only
  bypass behind a CDN.

## Hardening checklist

- Set `$retryAfterSeconds` to a realistic ETA so crawlers don't hammer the
  503 page.
- For long maintenance windows, use a window secret (`mm:down --secret …`)
  rather than the config-level key — the secret automatically dies when the
  window ends.
- Don't print secrets in deploy logs. The `mm:down` command intentionally
  prints them to the terminal so the operator sees them; CI logs are usually
  not the right place to retain that output.
- Rotate `Config\Maintenance::$secretBypassKey` periodically if you keep one
  pinned.
- Wire `maintenance.access_denied` to a SIEM/audit sink to spot brute-force
  attempts on the secret URL.

## Reporting a vulnerability

See [SECURITY.md](../SECURITY.md). Please use private reporting — do not file
public issues.
