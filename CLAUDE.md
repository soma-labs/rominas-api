<laravel-boost-guidelines>
=== .ai/rominas-architecture rules ===

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

=== .ai/rominas-testing rules ===

# Rominas — Testing

- Read `.claude/skills/testing-best-practices/` (`SKILL.md` and `rules/*.md`) before writing tests. Conventions in the neighbouring test files win over the skill.
- **Feature tests first.** Prove a change through the HTTP layer with a Pest feature test in `tests/Feature`. Write a unit test only for pure logic worth pinning on its own (like `ScoreCalculatorTest`), and never a unit test and a feature test for the same behaviour.
- **No tests for database seeders.** Check a seeder with Pint, PHPStan and a manual `db:seed --class=…` run.
- Assert the status explicitly (`assertStatus(201)`, not `assertOk()`), and cover the unhappy paths: validation errors, 401, 403, 404, 422.
- Assert the user-facing message text where it is part of the contract, and that a refused request changed nothing.
- Build data with factories and their named states (`Member::factory()->founding()->create()`); add a state rather than passing raw attributes repeatedly.
- Freeze or move time with `travelTo()` / `freezeTime()` and assert known literal values; never recompute an expectation with the code under test.
- Tests never send real e-mail: `Tests\TestCase` points the SMTP mailer at the array transport, and `phpunit.xml` forces `MAIL_DELIVERY_TRANSPORT=smtp` with an empty `BREVO_API_KEY`. Keep it that way.
- Run the narrowest tests that cover the change while developing, and the full suite (`php artisan test --compact`) before merging `dev` into `main`.

=== .ai/rominas-tooling rules ===

# Rominas — Tooling

- **Tests and anything that needs the database run in Sail.** The host PHP has no MySQL driver and `mysql` is a Compose service name, so: `vendor/bin/sail artisan test --compact --filter=…`, `vendor/bin/sail artisan migrate`, `vendor/bin/sail artisan ide-helper:models --write-mixin --no-interaction` (after model changes; `_ide_helper_models.php` is git-ignored and feeds PHPStan).
- **Pint and PHPStan run on the host, never through `sail exec`.** Inside the container they write their caches (`build/phpstan/`) as another user, and the next host run, including the pre-commit hook, then fails with "Could not write data to cache file". If it happens, clean up with `docker exec -u root rominas-api-laravel.test-1 sh -c 'find /var/www/html/build/phpstan -mindepth 1 ! -user 1000 -delete'`.
- Format after editing PHP: `vendor/bin/pint --dirty --format agent`.
- Static analysis is Larastan level 6 (`phpstan.neon`). While working, analyse only the touched paths: `vendor/bin/phpstan analyse app/Modules/<Module> --no-progress --memory-limit=1G`. A whole-app run starts one worker per CPU core and can exhaust the RAM of a small machine; cap it with a local neon that `includes` `phpstan.neon` and sets `parameters.parallel.maximumNumberOfProcesses: 2`, passed with `-c`.
- **Pre-commit hook:** `.githooks/pre-commit` runs Pint on the staged PHP files (re-staging its fixes) and Larastan on the app, and refuses the commit if Larastan fails. Enable it once per clone with `git config core.hooksPath .githooks`. Fix the errors it reports, even in files you didn't touch, rather than committing with `--no-verify`.
- Don't add or remove dependencies, or create new top-level folders, without the maintainer's approval.

=== .ai/rominas-workflow rules ===

# Rominas — Git Workflow

- **Work and commit on `dev`** in `rominas-api`, `rominas-admin`, `rominas-academy` and `rominas-voting`. `main` is the release line; the maintainer merges `dev` into `main` and pushes. (`rominas-deployment` is still worked on `main`.) Check `git branch --show-current` before committing.
- **Commit messages carry only the substance:** a subject line, then what changed and why. No closing recap ("tests pass", "docs updated") and no trailers or metadata (`Co-Authored-By`, session links).
- A change that touches several repos (say the API and the academy) is committed in each repo separately, and they are deployed together; say so in the module's `docs/` chapter when the order matters (for example "run `migrate`, deploy the API and the academy together").
- The frontends are separate Next.js repos next to this one, each with its own `CLAUDE.md` to read before working there. Their checks are `pnpm exec tsc --noEmit` and `pnpm lint`; there is no automated frontend testing, so a UI change comes with a short manual test procedure instead.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
