# Maintenance Mode for CodeIgniter 4

> Production-grade maintenance mode for CodeIgniter 4 — typed, testable, and ready for distributed deployments.

### Package

[![Latest Stable Version](https://img.shields.io/packagist/v/daycry/maintenancemode.svg?label=stable)](https://packagist.org/packages/daycry/maintenancemode)
[![Total Downloads](https://img.shields.io/packagist/dt/daycry/maintenancemode.svg)](https://packagist.org/packages/daycry/maintenancemode)
[![Monthly Downloads](https://img.shields.io/packagist/dm/daycry/maintenancemode.svg)](https://packagist.org/packages/daycry/maintenancemode)
[![PHP Version Require](https://img.shields.io/packagist/dependency-v/daycry/maintenancemode/php?color=8892bf)](https://packagist.org/packages/daycry/maintenancemode)
[![License](https://img.shields.io/github/license/daycry/maintenancemode)](https://github.com/daycry/maintenancemode/blob/master/LICENSE)

### Quality

[![PHPUnit](https://github.com/daycry/maintenancemode/actions/workflows/phpunit.yml/badge.svg)](https://github.com/daycry/maintenancemode/actions/workflows/phpunit.yml)
[![PHPStan](https://github.com/daycry/maintenancemode/actions/workflows/phpstan.yml/badge.svg)](https://github.com/daycry/maintenancemode/actions/workflows/phpstan.yml)
[![Psalm](https://github.com/daycry/maintenancemode/actions/workflows/psalm.yml/badge.svg)](https://github.com/daycry/maintenancemode/actions/workflows/psalm.yml)
[![Rector](https://github.com/daycry/maintenancemode/actions/workflows/rector.yml/badge.svg)](https://github.com/daycry/maintenancemode/actions/workflows/rector.yml)
[![Code Style](https://github.com/daycry/maintenancemode/actions/workflows/code-style.yml/badge.svg)](https://github.com/daycry/maintenancemode/actions/workflows/code-style.yml)
[![CodeQL](https://github.com/daycry/maintenancemode/actions/workflows/codeql.yml/badge.svg)](https://github.com/daycry/maintenancemode/actions/workflows/codeql.yml)
[![Coverage Status](https://coveralls.io/repos/github/daycry/maintenancemode/badge.svg?branch=master)](https://coveralls.io/github/daycry/maintenancemode?branch=master)

### Community

[![GitHub stars](https://img.shields.io/github/stars/daycry/maintenancemode?style=social)](https://github.com/daycry/maintenancemode/stargazers)
[![Donate](https://img.shields.io/badge/Donate-PayPal-blue.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

## Highlights

- 🛡 **Timing-safe bypass** — `hash_equals()` for every secret/cookie comparison.
- 🚦 **Multiple bypass paths** — IP / CIDR (IPv4 + IPv6), URL secret, signed cookie, route allow-list.
- 🗓 **Scheduled windows** — `--start` / `--end`, auto-deactivation on the next request.
- 📦 **Pluggable storage** — `cache` (Redis/Memcached/file via CI4 Cache) or `file` driver, switch with one config flag.
- 🔌 **Framework events** — `maintenance.activated`, `.deactivated`, `.bypassed`, `.access_denied`, `.scheduled.expired`.
- 🧱 **JSON or HTML** — content-negotiated 503 (or 302 redirect) per request.
- 🧪 **149 unit tests**, PHPStan + Psalm + Rector clean, PHP 8.2 / 8.3 / 8.4.

## Install

```bash
composer require daycry/maintenancemode
php spark mm:publish
```

Then wire the filter in `app/Config/Filters.php`:

```php
public array $aliases = [
    'maintenance' => \Daycry\Maintenance\Filters\Maintenance::class,
];

public array $globals = [
    'before' => ['maintenance'],
];
```

## Quick start

```bash
# Activate (with an allow-listed IP, a secret URL bypass, and a 30 min ETA)
php spark mm:down --message "Upgrading the database" \
                  --ip "203.0.113.5" \
                  --secret "ops-token" \
                  --duration 30

# Inspect state
php spark mm:status

# Bring the site back up
php spark mm:up
```

```bash
# Schedule a window
php spark mm:down --start "2026-05-10 02:00" --end "2026-05-10 04:00"

# Skip 503 for healthchecks (config option)
$config->bypassRoutes = ['/health', '/api/webhooks/*'];

# Force JSON response on /api/* routes
$config->jsonRoutes = ['/api/*'];

# Render a custom view for THIS window
php spark mm:down --render errors/html/branded_503

# Redirect to a status page instead of 503
php spark mm:down --redirect https://status.example.com
```

## Documentation

Everything else lives in [`docs/`](docs/README.md):

- [Installation & first run](docs/installation.md)
- [Full configuration reference](docs/configuration.md)
- [CLI commands](docs/commands.md)
- [Bypass methods & priority](docs/bypass.md)
- [Filter & framework events](docs/filters-and-events.md)
- [Storage drivers (cache / file)](docs/storage-drivers.md)
- [Architecture](docs/architecture.md)
- [Security model](docs/security.md)
- [Troubleshooting](docs/troubleshooting.md) · [FAQ](docs/faq.md) · [Upgrade guide](docs/upgrade.md) · [Roadmap](docs/roadmap.md)

### Examples

- [Basic maintenance](docs/examples/basic-maintenance.md)
- [Scheduled window](docs/examples/scheduled-window.md)
- [JSON 503 for APIs](docs/examples/api-json-response.md)
- [Slack / PagerDuty notifications](docs/examples/webhook-notifications.md)
- [Multi-tenant *(planned)*](docs/examples/multi-tenant.md)
- [Cloudflare / CDN trusted proxies *(planned)*](docs/examples/cdn-cloudflare.md)

## Quality bar

This package treats the toolchain as part of the contract. Every push runs:

| Workflow | Tool | What it guards |
| --- | --- | --- |
| [PHPUnit](.github/workflows/phpunit.yml) | PHPUnit · matrix PHP 8.2 / 8.3 / 8.4 + lowest-deps | Behaviour |
| [Code Style](.github/workflows/code-style.yml) | PHP-CS-Fixer (CodeIgniter 4 ruleset) | Formatting |
| [PHPStan](.github/workflows/phpstan.yml) | PHPStan level 6 | Static types |
| [Psalm](.github/workflows/psalm.yml) | Psalm error level 6 | Static types & dead code |
| [Rector](.github/workflows/rector.yml) | Rector dry-run | Refactor drift |
| [CodeQL](.github/workflows/codeql.yml) | GitHub CodeQL (Actions + JS/TS) | Supply-chain |

Reproduce them locally with `composer ci`.

## Contributing

Bug reports, feature requests and PRs are welcome. Please read
[CONTRIBUTING.md](CONTRIBUTING.md) and the [Code of Conduct](CODE_OF_CONDUCT.md)
first. For security issues, follow [SECURITY.md](SECURITY.md) — please **don't**
file them as public issues.

## License

[MIT](LICENSE) · made for the CodeIgniter 4 community.
