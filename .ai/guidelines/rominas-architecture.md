# Rominas — Architecture & Conventions

What Rominas is, the module map, domain rules and GDPR decisions: `docs/ecosystem.md`. Each built module is documented in `docs/` (start at `docs/README.md`); keep those chapters in sync with every change.

## Modular DDD

- Every bounded context is a self-contained module under `app/Modules/<Module>/` (namespace `Rominas\<Module>\`), holding its own Actions, DataTransferObjects, `Model/`, Controllers, QueryBuilders, Policies, Requests, Resources, Factories and Enums. No global `app/Models`, `app/Http/Requests` and the like.
- **Actions**: one class, one operation (`CreateMemberAction`). No multi-purpose services.
- **DTOs** cross every module boundary; never pass raw arrays between layers. A `Factories/…DataFactory` builds a DTO from a Form Request.
- **QueryBuilders**: each model has its own Eloquent builder (`MemberQueryBuilder`) with named, reusable scopes.
- **Controllers are thin**: resolve input (Form Request), delegate to an Action, return a Resource.
- Validate input with **Form Requests**, return **Resource** classes, authorise every action with a **Policy** (no inline `Gate::allows`).
- Multi-step writes run in `DB::transaction(fn () => …)`.
- Prefer Laravel's own features (jobs, events, notifications, policies, queues) over custom solutions. Long-running work (e-mails, exports) is queued.

## PHP & Laravel style

- `declare(strict_types=1);` in every PHP file; PSR-12 / PER via Pint.
- Descriptive, intention-revealing names, even if long.
- Named arguments when a call takes several parameters; constructor promotion and `readonly` where they fit; enums instead of constant sets.
- Models: `$fillable` (whitelist), never `$guarded`; cast every boolean, date, enum and JSON column.
- Eager-load relations to avoid N+1; paginate lists; never return unbounded collections.

## Database & migrations

- Every migration is reversible: `down()` undoes `up()` exactly.
- Never edit an existing migration; add a new one.
- Index every foreign key and frequently queried column; `foreignId()->constrained()->cascadeOnDelete()` for foreign keys.

## Project-specific rules

- **User-facing text is translated by the API.** Wrap every message a member, voter or admin can read in `__()`, with the English text as the key, and add the Romanian line to `lang/ro.json` (validation-rule messages: `lang/ro/validation.php`). Frontends display the API's `message` / `errors` as they are. Details: `docs/architecture.md` §7.
- **Datetimes are UTC.** The API stores and sends UTC; there is no `APP_TIMEZONE`. Each frontend converts for display.
- **Artisan commands** are named `romias:<name>` and registered in `bootstrap/app.php`.
- **New settings** go in `.env.example` under the "Rominas" heading at the end, and are read via `config()`, never `env()` in code.
- **Secrets and tokens:** a magic-link or voting-link token is never logged. A payload factory that takes a token marks it `#[SensitiveParameter]`. Personal data is hashed or pseudonymised wherever plaintext isn't needed (GDPR, see `docs/ecosystem.md` §6).
- **E-mails:** one factory per e-mail in `app/Modules/Delivery/Factories/` building a `MailPayload`; templates wrap their content in `<x-email.layout>` / `<x-email.button>` (table-based, so classic Outlook renders them).
- User-visible names say **Romias**; internal names (namespace `Rominas\`, repos, database) keep the old spelling on purpose.
