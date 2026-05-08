# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 3.x     | :white_check_mark: (current) |
| 2.x     | :white_check_mark: (security fixes only) |
| < 2.0   | :x:                |

## Reporting a Vulnerability

**Do not open public issues for security vulnerabilities.**

If you discover a security issue, please report it privately:

- Use [GitHub Security Advisories](https://github.com/daycry/maintenancemode/security/advisories/new)
  to file a private report. This is the preferred channel.
- Or email the maintainer (see [`composer.json`](composer.json) `authors` for the address).

Please include:

- A description of the issue and the impact you believe it has.
- Steps to reproduce, or a proof-of-concept.
- Affected versions, if you've narrowed it down.
- Any fix you have in mind (optional).

## What to expect

- We aim to acknowledge your report within **72 hours**.
- We'll work with you privately on a fix and a coordinated disclosure timeline.
- A patched release will land before the advisory becomes public.
- You'll be credited in the release notes (unless you'd rather stay anonymous).

## Threat model

This package guards an HTTP endpoint while maintenance is active. The threats
we explicitly defend against are:

- **Timing attacks** on bypass-secret comparison (mitigated via `hash_equals()`).
- **Cookie forgery** for bypass (mitigated by comparing a 32-byte random
  `cookie_value`, not the cookie name).
- **CIDR injection** in `mm:down --ip` (validated for shape and prefix range).
- **Race conditions** when writing maintenance state to disk (mitigated by
  `flock(LOCK_EX)`).

We do **not** defend against attackers who already have access to the server's
filesystem, the cache backend, or the application config. If your secret leaks
into version control or logs, rotate it.
