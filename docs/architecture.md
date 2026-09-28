# Module architecture

How `rominas-api` is organized: a **Modular DDD** layout ported from the `door` reference project.
This chapter is the "how we structure code" guide; for *what* the entities are see
[domain-model.md](domain-model.md).

## 1. The module system in one line

Everything hinges on a single PSR-4 mapping in `composer.json`:

```json
"Rominas\\": "app/Modules/"
```

That is the **entire** module mechanism. There is **no per-module `ServiceProvider`**, no module
manifest, no package discovery. Modules are plain directories under `app/Modules/<Module>/` in the
`Rominas\<Module>\…` namespace; everything else (routes, migrations, factories, config, event/command
wiring) is **centralized** in the standard Laravel locations and the three app providers.

Cross-cutting wiring lives in:

- `app/Providers/AppServiceProvider.php` — bindings (e.g. the Delivery transport), rate limiters, and
  `Factory::guessFactoryNamesUsing()` (models are `Rominas\…\Model\X`, factories stay in
  `Database\Factories`).
- `app/Providers/AuthServiceProvider.php` — the `super_admin` `Gate::before` bypass.
- `app/Providers/EventServiceProvider.php` — explicit event→listener map (auto-discovery does not
  scan `app/Modules`).

## 2. Per-entity skeleton

A module contains one folder per entity; each entity repeats the same building-block skeleton
(mirrors `door`). Example — `Catalog/Artist/`:

```
app/Modules/Catalog/Artist/
├── Actions/          CreateArtistAction, UpdateArtistAction, DeleteArtistAction
├── Controllers/      ArtistsController        (thin: input → Action → Resource)
├── DataTransferObjects/  ArtistData           (typed; never pass raw arrays across boundaries)
├── Factories/        ArtistDataFactory        (builds the DTO from a validated Request)
├── Model/            Artist
├── Policies/         ArtistPolicy             (attached via #[UsePolicy])
├── QueryBuilders/    ArtistQueryBuilder       (search/sort + permission scoping)
├── Requests/         CreateArtistRequest, UpdateArtistRequest
└── Resources/        ArtistResource
```

Module-shared code sits at the module root (e.g. `Catalog/Enums/NomineeType.php`). Add `Enums/`,
`Jobs/`, `Observers/`, `Commands/`, `Concerns/`, `Meta/` only when an entity needs them.

**Model conventions** — native attributes replace boilerplate: `#[Fillable([...])]`,
`#[Hidden([...])]`, `#[UsePolicy(...)]`, `#[ObservedBy([...])]`. Each model overrides
`newEloquentBuilder()` + `static query()` to return its typed QueryBuilder, and carries a
`@mixin IdeHelper<Model>` tag (see §5). Slugs are auto-generated in a `booted()` `saving` hook.

**Query builders** compose the shared traits from `app/Modules/Shared/Concerns/`
(`QueryBuilderSearchableTrait` → `->search()`, `QueryBuilderSortableTrait` → `->orderBy()` guarded by
an allow-list) and add `actionableByUser()`/`visibleToUser()` for permission scoping.

## 3. Where non-module code lives (centralized)

| Concern | Location | Pattern |
| --- | --- | --- |
| Routes | `routes/api.php` + `routes/api/admin/<x>.php` | admin group `auth:sanctum` + `audit` (the `Audit` middleware `RecordAuditTrail`, aliased in `bootstrap/app.php`; also on the audited auth/academy routes) + `/admin`; one per-concern file per entity, mounted with `Route::prefix()->group(__DIR__.'/api/admin/x.php')`; `can:` middleware inline |
| Migrations | `database/migrations/` | standard timestamped files |
| Eloquent factories | `database/factories/` | `class XFactory extends Factory { protected $model = X::class; }` |
| Seeders | `database/seeders/` | `RoleSeeder`, `PermissionSeeder`, called from `DatabaseSeeder` |
| Module config | `config/<module>.php` | e.g. `config/delivery.php`, `config/permission.php` |
| Blade (emails) | `resources/views/emails/` | Delivery payload factories render these |

## 4. Adding a new module (port-and-adapt from `door`)

The house workflow is **port from `/home/razvan/projects/door/door-api`, don't build greenfield**.
For a flat entity, `door`'s `Catalog/MaturityRating` is the canonical 12-file template. The recipe:

1. Inspect the matching `door` module/entity; copy its files into `app/Modules/…`.
2. Rewrite the namespace `Door\ → Rominas\` (and the `Shared\Concerns` imports).
3. Drop anything not wanted yet (relations, taxonomy pivots, coupled payload factories).
4. Add the Eloquent factory + migration + route file, and mount the route in `routes/api.php`.
5. Add the permission name to `PermissionSeeder` and grant it to the right role(s).
6. Regenerate ide-helper (§5), then run the quality gate (§6) and write Pest tests.

Greenfield modules with no `door` equivalent (e.g. `Editions`, `Categories`) still follow the same
skeleton and conventions.

## 5. ide-helper & static analysis

Model docblocks carry `@mixin IdeHelper<Model>` tags; the stubs live in `_ide_helper_models.php`
(git-ignored) and PHPStan reads them via `scanFiles` in `phpstan.neon`. **After adding or changing a
model, regenerate the stubs** — inside the Sail container:

```bash
docker exec rominas-api-laravel.test-1 php artisan ide-helper:models --write-mixin --no-interaction
```

> ⚠️ Use `--write-mixin`. The plain `-N`/`--nowrite` mode writes *duplicate* model classes into
> `_ide_helper_models.php` that shadow the real models — PHPStan then reports phantom "undefined
> method `can()`/`createToken()`" errors and no `IdeHelper*` stubs. `--write-mixin` emits the
> `IdeHelper*` stubs the `@mixin` tags + `scanFiles` expect.

PHPStan runs at **level 6** (Larastan). We keep the tree clean by fixing types, **not** by baselining
or `@phpstan-ignore`.

## 6. Tooling & running things

- **Format:** `vendor/bin/pint` (PER preset) · **Analyse:** `vendor/bin/phpstan analyse` (level 6).
  Both are enforced by the versioned pre-commit hook (`.githooks/pre-commit`; enable once with
  `git config core.hooksPath .githooks`).
- **Database work runs inside the Sail container** — the host PHP has no `pdo_mysql`. Prefix with
  `docker exec rominas-api-laravel.test-1 …` (e.g. `php artisan migrate`, `php artisan test`).
- **Tests:** Pest, `RefreshDatabase`, feature-first. Tests authenticate via the `actingAsSuperAdmin()`
  helper in `tests/Pest.php` (bypasses gates) or `Sanctum::actingAs()` for permission-boundary cases.
- **DB inspection (optional):** `./dbhub-start.sh` from the `rominas/` parent starts the dbhub server
  the DB MCP connects to (`rominas-api/dbhub.toml`, `localhost:3306`).

## 7. Localization

The API is the **single translation point** for user-facing error text — a frontend displays the
`message` / `errors` an endpoint returns rather than inventing its own copy, so every client (academy,
voting, admin) gets one translation for free.

- `Rominas\Shared\Middleware\SetLocaleFromRequest` (registered on the whole `api` group in
  `bootstrap/app.php`) sets the app locale from `Accept-Language`, matched against
  `config('app.supported_locales')` (currently `['en', 'ro']`); anything else keeps `config('app.locale')`
  (`en`).
- **Every new user-facing message must be wrapped in `__()`** — `ValidationException::withMessages([...])`,
  an `abort(status, message)`, a plain `response()->json(['message' => ...])`. Keep the English text as
  the translation key (Laravel's JSON translation convention) and add the Romanian line to `lang/ro.json`.
  A message needing a count/name uses a `:placeholder` (`__('... :count ...', ['count' => $n])`) or, for
  real pluralization (singular/few/other — Romanian's plural rule needs three forms), `trans_choice()`
  with `|`-separated forms and a matching `lang/ro.json` entry.
- Laravel's own validation rule messages come from `lang/ro/validation.php` — hand-written and covering
  only the rules this app's Form Requests actually use (`required`, `email`, `string`, `array`, `max`,
  `min`, `distinct`, `present`, plus `attributes` for human field names). Anything not listed there falls
  back to the published `lang/en/validation.php` per-key, via `fallback_locale` — no need to duplicate the
  whole file. Re-run `php artisan lang:publish` if the English baseline ever needs refreshing.
- A route that throttles (`RateLimiter::for(...)`) needs its own `->response()` callback if it should be
  translated: `ThrottleRequestsException`'s built-in message ("Too Many Attempts.") is a raw string, never
  passed through the translator. See the `magic-request` limiter in `AppServiceProvider` for the pattern.
