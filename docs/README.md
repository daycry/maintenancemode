# Documentation

Welcome to the `daycry/maintenancemode` docs. The project root [`README.md`](../README.md)
gives you the 30-second pitch and a quick start; this folder is the long-form
reference.

## Where to start

- **First time?** → [installation.md](installation.md) → [examples/basic-maintenance.md](examples/basic-maintenance.md)
- **Configuring?** → [configuration.md](configuration.md)
- **Need a CLI command reference?** → [commands.md](commands.md)
- **Bypass logic confusing you?** → [bypass.md](bypass.md)
- **Building a SaaS / API / scheduled window?** → see `examples/`

## Topic guides

| Guide | What's inside |
| --- | --- |
| [installation.md](installation.md) | `composer require`, publishing the config, first run |
| [configuration.md](configuration.md) | Every field of `Config\Maintenance`, with defaults and examples |
| [commands.md](commands.md) | `mm:down`, `mm:up`, `mm:status`, `mm:migrate`, `mm:publish`, all flags |
| [bypass.md](bypass.md) | IP / CIDR / secret URL / cookie — order, semantics, security |
| [filters-and-events.md](filters-and-events.md) | Wiring the filter, listening to `maintenance.*` events |
| [storage-drivers.md](storage-drivers.md) | `file` vs `cache`, when to pick which, migrating between them |
| [architecture.md](architecture.md) | Internal flow diagram (Filter → Service → Storage) |
| [security.md](security.md) | Threat model and hardening notes |
| [troubleshooting.md](troubleshooting.md) | Common pitfalls + diagnosis |
| [faq.md](faq.md) | Quick answers to recurring questions |
| [upgrade.md](upgrade.md) | Migration notes between major versions |
| [roadmap.md](roadmap.md) | What's next and how to influence it |

## Examples

Real, runnable end-to-end recipes:

- [basic-maintenance.md](examples/basic-maintenance.md) — the happy path
- [scheduled-window.md](examples/scheduled-window.md) — `--start` / `--end` times
- [api-json-response.md](examples/api-json-response.md) — JSON 503 for APIs
- [webhook-notifications.md](examples/webhook-notifications.md) — Slack / PagerDuty on maintenance events
- [multi-tenant.md](examples/multi-tenant.md) — per-tenant maintenance *(planned)*
- [cdn-cloudflare.md](examples/cdn-cloudflare.md) — getting the real client IP behind a CDN *(planned)*

> *Items marked "planned" still depend on roadmap features (multi-tenant
> isolation, first-class trusted-proxy presets). They live here as stubs so
> the documentation structure is in place when those features land. Each
> stub also includes a workaround you can use today.*
