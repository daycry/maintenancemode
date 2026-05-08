# CLI commands

All commands live under the `mm:` namespace. Run `php spark list` to see them
in the global list, or `php spark help mm:down` (etc.) for usage of a single
command.

## `mm:down` — Activate maintenance

```
php spark mm:down [--message "..."] [--ip "1.2.3.4 10.0.0.0/8"] \
                  [--duration 60] [--secret token] [--cookie name] \
                  [--start "2026-05-10 02:00"] [--end "2026-05-10 04:00"] \
                  [--render errors/html/branded] [--redirect https://status...]
```

| Flag | Type | Default | Description |
| --- | --- | --- | --- |
| `--message` | string | config `defaultMessage` | Message displayed on the 503 page. |
| `--ip` | space-separated list | `127.0.0.1` (in test/non-interactive runs) | IPs and CIDR ranges allowed through. Validated for shape and prefix range (0–32 v4 / 0–128 v6). Invalid entries are warned and dropped. |
| `--duration` | int (minutes) | config `defaultDurationMinutes` (60) | ETA shown to users; also stored as `estimated_end` for monitoring. |
| `--secret` | string | none | Per-window bypass token. Visit `<site>?maintenance_secret=<token>` to bypass. |
| `--cookie` | string | random 8 chars | Name of the bypass cookie. The package always generates a high-entropy `cookie_value` (32 random bytes hex). The activation banner prints both. |
| `--start` | datetime (`strtotime()`) | none | Window does not block traffic until this time. See [scheduled-window example](examples/scheduled-window.md). |
| `--end` | datetime (`strtotime()`) | none | Window auto-deactivates on the next request after this time. Must be strictly after `--start` if both are set. |
| `--render` | view name | none | Override the 503 view for THIS window only. Wins over `templateByEnv` and `customTemplate`. |
| `--redirect` | URL | none | When the request is denied, 302-redirect to this URL instead of returning 503. |

When run interactively (no flags, real TTY), `mm:down` will prompt for the
core fields. In CI / scripts, pass flags or rely on the test-environment
defaults.

The command emits a `maintenance.activated` framework event with the saved data.

## `mm:up` — Deactivate maintenance

```
php spark mm:up
```

Removes the maintenance state from the active driver and emits a
`maintenance.deactivated` event. Idempotent: no-op if maintenance is already
off.

## `mm:status` — Inspect current state

```
php spark mm:status [--show-public-ip]
```

Shows whether the application is live or in maintenance, the storage backend
in use, the saved data, the allowed IP table, and an evaluation of every
bypass method against the *current* CLI session.

| Flag | Description |
| --- | --- |
| `--show-public-ip` | Try to detect the host's public IP via an external service (timeouts: 2 s connect / 3 s total). Off by default — no implicit network calls. |

## `mm:migrate` — Move state between drivers

```
php spark mm:migrate [--force] [--clear]
```

| Flag | Description |
| --- | --- |
| `--force` | Migrate even if `$useCache` (legacy) is `false`. |
| `--clear` | Wipe state from BOTH cache and file storage. Asks for confirmation unless `ENVIRONMENT=testing`. |

Use this when moving from `file` to `cache` (or vice versa) on a running
deployment without losing the current window.

## `mm:preview` — Render the 503 view without activating

```
php spark mm:preview [--message "..."] [--template "errors/html/x"] [--output path]
```

| Flag | Description |
| --- | --- |
| `--message` | Override the message rendered in the preview. Defaults to `defaultMessage`. |
| `--template` | View name to render. Defaults to whatever `MaintenanceService::resolveTemplate()` would pick. |
| `--output` | Write the rendered HTML to a file instead of stdout. Useful for visual diffs in PRs. |

The command never touches storage; it just runs the view with stub data. Use
it while iterating on a custom template:

```bash
php spark mm:preview --template errors/html/branded --output build/preview.html
open build/preview.html
```

## `mm:publish` — Vendor publish

```
php spark mm:publish
```

Copies the package's `Config\Maintenance` to `app/Config/Maintenance.php` and
the 503 view template to `app/Views/errors/html/error_503.php`. Re-running is
non-destructive — it asks before overwriting.
