# Rominas API

**Rominas** (Romanian Music Industry Awards) is a yearly, Grammy-like awards event for the Romanian
music industry. Each **edition** runs a lifecycle from academy nominations through public voting to a
final, committee-reviewed result set that is published and then archived.

This repository — `rominas-api` — is the **Laravel backend** and the single source of truth for the
whole domain: editions, categories, the nominatable catalog, academy nominations, public voting,
scoring, results and access control all live here. It is the backbone for several audience-specific
frontends:

| App | Role |
| --- | --- |
| **rominas-api** (this repo) | API-first Laravel backend; the only app that talks to the database |
| Academy Dashboard | Academy members & critics — nominations (Next.js SPA, API client) |
| Public Voting | General public, one-time link — the ballot (Next.js SPA, API client) |
| Management Dashboard | Staff, admins, custodian — administration & monitoring (Next.js SPA, API client) |

The public presentation site is a separate WordPress project, out of scope for this repo.

## Tech stack

- **PHP 8.5 / Laravel 13**, API-first.
- **Auth:** Laravel **Sanctum** (token-based); spatie/laravel-permission with wildcard permissions.
  Admin login is username+password; participant (academy/critic) magic-link/OTP and accountless
  public voting are planned.
- **Messaging:** transactional email behind a transport seam (SMTP; Brevo opt-in).
- **Testing:** Pest. **Quality:** Laravel Pint (PER), Larastan (level 6).
- **Local dev:** Laravel Sail (MySQL 8.4).

## Architecture — Modular DDD

Self-contained modules under `app/Modules/<Module>/`, each namespaced `Rominas\<Module>\…`, ported
and adapted from the `door` reference project. A module owns its `Model/`, `Actions/`,
`DataTransferObjects/`, `Factories/`, `QueryBuilders/`, `Requests/`, `Resources/`, `Policies/` and
`Controllers/`; controllers stay thin (input → Action → Resource). Larger areas split into
per-entity submodules (e.g. `Catalog/Artist/…`). See [docs/architecture.md](docs/architecture.md).

| Module | Responsibility | Status |
| --- | --- | --- |
| `Users` / `Roles` / `Permissions` | Admin accounts + role/permission access control | built |
| `Auth` | Sanctum admin login / logout | built |
| `Taxonomies` | Reusable classification vocabulary (genre, region, tag) | built |
| `Editions` | Yearly award editions + lifecycle | built |
| `Categories` | Award categories, scoped to an edition, typed by nominee kind | built |
| `Catalog` | Nominatable entities: artists, bands, venues, songs, albums | built |
| `Delivery` | Transactional messaging (SMTP / Brevo) | built |
| `Shared` | Cross-module query-builder concerns | built |
| `Academy`, `CriticsChoice`, `Voting`, `Scoring`, `Results`, `FraudMonitoring`, `Audit` | Nominations, public voting, scoring, results, anti-fraud, audit | planned |

## Documentation

Deeper documentation lives in [`docs/`](docs/README.md). Start with the
[domain model](docs/domain-model.md) for every entity and its relationships, then
[module architecture](docs/architecture.md), [access control & auth](docs/access-control.md), the
[edition lifecycle](docs/edition-lifecycle.md) and the [audit trail](docs/audit.md). Ecosystem-level decisions and open questions are
tracked in the ecosystem [`CLAUDE.md`](../CLAUDE.md).

## Getting started

Local development uses **Laravel Sail**; run artisan/tests **inside the container** (the host PHP has
no `pdo_mysql`).

```bash
composer install
cp .env.example .env && php artisan key:generate
./vendor/bin/sail up -d                       # MySQL 8.4 + app container
./vendor/bin/sail artisan migrate --seed      # schema + baseline roles/permissions
./vendor/bin/sail artisan test                # Pest suite

vendor/bin/pint                               # format (PER)
vendor/bin/phpstan analyse                    # static analysis (Larastan level 6)
```

A versioned pre-commit hook lints (Pint) and analyses (Larastan) staged PHP; enable it once with
`git config core.hooksPath .githooks`.
