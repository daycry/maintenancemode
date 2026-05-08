# Contributing

Thanks for taking the time to contribute. This document is short on purpose —
follow the spirit, not the letter.

## Reporting bugs or asking for features

- Search existing [issues](https://github.com/daycry/maintenancemode/issues) first.
- For bugs, the issue template will ask for: PHP / CodeIgniter version, the
  config you're using, the command you ran, and the actual vs expected behaviour.
- For security issues please **do not** open a public issue — see
  [SECURITY.md](SECURITY.md).

## Local setup

```bash
git clone https://github.com/daycry/maintenancemode.git
cd maintenancemode
composer install
```

Run the full quality bar locally before opening a PR:

```bash
composer ci          # cs + analyze + test
```

Or run each step individually:

```bash
composer cs          # PHP-CS-Fixer dry-run
composer cs-fix      # auto-fix style
composer analyze     # PHPStan
composer test        # PHPUnit
```

## Pull requests

- Fork, branch (`feat/short-name` or `fix/short-name`), commit, push, PR.
- Keep PRs focused: one feature or fix per PR.
- Add or update tests for any behavioural change. PRs that lower coverage on
  `src/Services` or `src/Storage` will be asked to add coverage.
- Update `CHANGELOG.md` under `## [Unreleased]` with a one-line summary.
- Update `docs/` if the change affects user-visible behaviour.

## Conventional commits

We follow [Conventional Commits](https://www.conventionalcommits.org/):

- `feat: add scheduled maintenance windows`
- `fix: timing-safe cookie comparison`
- `docs: clarify bypass priority`
- `refactor: extract MaintenanceService`
- `test: cover IpChecker IPv6 edge cases`
- `chore(deps): bump phpstan to ^1.11`

Dependabot already uses these prefixes for its automated PRs.

## Code style

- PHP-CS-Fixer config is `.php-cs-fixer.dist.php` (CodeIgniter 4 ruleset).
- PHPStan runs at level 6 (see `phpstan.neon.dist`).
- New code should be type-hinted; prefer `readonly` for value objects.
- No comments that describe *what* the code does — only *why* when non-obvious.

## Testing

- New code lives under `src/`; tests under `tests/Maintenance/`.
- Use `Services::injectMock('request', $mock)` to test bypass logic — setting
  `$_GET` directly does not propagate to the cached request singleton.
- The shared `Tests\Support\TestCase` resets services and settings for you.

## Releases

Maintainers cut releases by:

1. Updating `CHANGELOG.md` (rename `[Unreleased]` to the new version).
2. Tagging `vX.Y.Z` on `master`.
3. Drafting GitHub release notes from the changelog entry.

Semantic versioning rules:

- `X` (major) — breaking API change.
- `Y` (minor) — backwards-compatible feature.
- `Z` (patch) — backwards-compatible fix.
