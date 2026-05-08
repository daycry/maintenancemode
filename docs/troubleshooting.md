# Troubleshooting

## "Application is already in maintenance mode"

```
**** Application is already in maintenance mode. ****
```

`mm:down` refuses to overwrite an active window. Take it out first:

```bash
php spark mm:up
php spark mm:down --message "..."
```

If `mm:up` reports "Application is already live" but `mm:status` keeps showing
maintenance, you likely have stale state in the *other* driver:

```bash
php spark mm:migrate --clear
```

## "Maintenance file is not valid JSON"

A worker crashed mid-write before Sprint 1's `flock()` was in place, or a
manual edit produced bad JSON. Recovery:

```bash
php spark mm:migrate --clear   # nuke both backends
php spark mm:down --message "Recovering"
```

## My IP / CIDR isn't bypassing

1. Check what `Services::request()->getIPAddress()` actually returns.
   Behind a CDN this is the proxy, not the user. Configure
   `Config\App::$proxyIPs` and `$proxyIPHeader` (e.g. Cloudflare's
   `CF-Connecting-IP`) before relying on IP bypass.
2. Run `php spark mm:status` — the "Access Status from CLI" block evaluates
   each bypass method against the current session and tells you why it's
   rejected.
3. CIDR with an out-of-range prefix is silently dropped at `mm:down` time
   (you'll see a `Warning:` line). `10.0.0.0/40` will not be saved.

## Secret URL bypass returns 503 even with the right value

The most common cause in custom code is **modifying `$_GET` after
`Services::request()` has been resolved.** The request reads its globals
during construction; later mutations to `$_GET` do not propagate.

In tests, use `Services::injectMock('request', $mock)` instead — see
`tests/Maintenance/SecurityTest.php` for the pattern.

In production, this isn't an issue: query parameters arrive with the request.

## Cookie bypass doesn't work

Make sure the cookie's **value** matches the `cookie_value` printed by
`mm:down`. The legacy behaviour where the cookie's value had to equal its
name is gone — the new value is a 64-char hex string.

```
Cookie bypass: set cookie 'WAh0z8x3' with value '44746b03e6...e1'
```

Set that cookie on maintainer browsers (via your reverse proxy, an admin
endpoint, or browser devtools) before testing.

## CI / GitHub Actions doesn't run

Until the v3 fix, the workflow triggered on `main` only. If your default
branch is `master` and CI seems silent, pull v3+ and confirm the workflow
contains:

```yaml
on:
  push:
    branches: [ master, main ]
  pull_request:
    branches: [ master, main ]
```

## PHPStan complains about CodeIgniter helpers

The bundled `phpstan.neon.dist` declares ignore patterns for
`helper`, `setting`, `log_message`, `lang`, `esc`, `service`, `base_url`,
`site_url`, `cookie`, `get_cookie`, `random_string`, `command`, `config` and
the framework constants. If you fork the package and add your own helpers,
extend the list rather than dropping the level.

## Tests pass locally but fail on CI

99% of the time this is **Windows-vs-Linux line endings**. Your local CRLF
files get converted to LF on commit (or vice versa), and PHP-CS-Fixer's
`line_ending` rule catches it. Run:

```bash
composer cs-fix
```

before pushing.
