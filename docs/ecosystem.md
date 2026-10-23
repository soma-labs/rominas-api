# Rominas — Ecosystem Guide

> Ecosystem-level guide for the **Rominas** (Romanian Music Industry Awards)
> project: the API and the three frontends. It lives in the API repo
> (`ecosystem.md`) because the API is the single source of truth.
> Read this for *what Rominas is* and the decisions specific to it; the API's
> working rules (architecture, testing, tooling, git workflow) are the Rominas
> guidelines in `rominas-api/.ai/guidelines/`, which Laravel Boost merges into the
> API's `CLAUDE.md` and `AGENTS.md`. Each frontend has its own `CLAUDE.md`.
>
> This is a living document. When a decision here conflicts with newer
> information, update this file.
>
> **Renamed (2026-10-06/07): the event is now called "Romias" and lives on `romias.ro`.**
> Anything a user reads (UI text, e-mails, the website) says **Romias**. The old domain
> `rominas.ro` only redirects. **Internal names keep the old spelling on purpose** and are not
> to be renamed: the repos and folders (`rominas-api`, …), the PHP namespace `Rominas\`, the
> `rominas` Linux user, the `rominas_wp` database. This guide still says "Rominas" where it
> means the project. How the move was done: `domain-change-plan.md` in the maintainer's `rominas/` parent folder (not in git).
> **Exception (2026-10-13):** the API's member and voter e-mails use the client's final texts
> (`E-mail Templates - finale ROMIAS.xlsx`, kept by the maintainer outside git), which name the event in full, "The Romanian Music
> Industry Awards". Keep them as the sheet has them.
> **Exception (2026-10-14):** the API's artisan commands use the new name, `romias:*`.

---

## 1. What Rominas is

Rominas is a yearly awards event for the Romanian music industry — conceptually
similar to the Grammys. Each yearly **edition** runs a lifecycle from academy
nominations through public voting to a final, committee-reviewed result set that
is published and then archived.

The ecosystem is made of four subprojects:

| # | Subproject | Audience | Nature |
|---|------------|----------|--------|
| 1 | **Academy Dashboard** | Academy members | SPA on the API |
| 2 | **Public Voting** | General public (one-time link) | SPA on the API |
| 4 | **Management Dashboard** | Staff, admins, custodian | SPA on the API |
| — | **Website** | Public | WordPress presentation site — **out of scope for this repo** |

> Numbering follows the original brief (the website is #3). We are modeling
> subprojects **1, 2 and 4** first.

---

## 2. Architecture at a glance

- **One Laravel API is the backbone and the single source of truth** for the
  entire domain. It lives in **`rominas/rominas-api/`** (its own repo). All
  subprojects consume it; no domain logic lives in the frontends.
- **Frontends are separate SPA repos** (**Next.js / React**), one per audience:
  Academy Dashboard, Public Voting, Management Dashboard. Each gets its own
  `CLAUDE.md`. (Matches the `door` reference project's `door-admin` /
  `door-frontend` Next.js apps.)
- **Repo layout:** `rominas/` is the parent folder; each subproject is a **sibling
  repo** inside it (`rominas-api/`, plus the frontends, e.g. `rominas-academy/`,
  `rominas-voting/`, `rominas-admin/`). This file is the **ecosystem-level
  overview**; per-repo specifics live in each repo's own `CLAUDE.md`.
- **Modular DDD** (see `.ai/guidelines/rominas-architecture.md`): self-contained modules under
  `app/Modules/`, each encapsulating its own Actions, DTOs, `Model/`,
  Controllers, QueryBuilders, Policies, Requests, Resources and Factories.
  - This project uses the standard **`app/`** folder (Modular DDD), **not** the
    `src/` layered layout.
- **Single shared database** inside the API (implied by the single-backbone
  decision).
- **Auth:**
  - **Admin / management dashboards:** Laravel **Sanctum** bearer tokens via
    **email + password** login (`POST /api/authenticate` → `{userId, token}`;
    `POST /api/logout`). Authorization is by policies / `$user->can()`, not token
    abilities. (OTP was considered but never built; the API implements password
    login for admin.)
  - **Academy members:** passwordless **magic-link** on the `member` guard
    (`/api/academy/auth/magic/{request,verify}`). The magic-link primitive is guard-agnostic, but no
    other guard uses it (the `critic` guard once planned is not being built). Magic-link is
    **member-only** — not used for admin.
  - **Public voting:** **one-time, signed, expiring links** — no account. A
    voter requests a link by email, votes once, and cannot request another.

---

## 3. Module map (`app/Modules/…`)

Bounded contexts we can already name. Each follows the co-located building
blocks of the Modular DDD layout.

- **`Editions`** — edition lifecycle (see §8); only **one active edition at a
  time**; mandatory start/end datetimes; categories belong to an edition.
- **`Categories`** — CRUD award categories, scoped to an edition. Each has a **kind**, fixed at creation:
  `ranked` (the normal flow below, the only kind with a nominee type), `propose_or_vote` or `appointed`
  (the two special kinds, see `SpecialAwards`). The ballot, the shortlist, public voting and scoring handle
  ranked categories only.
  Each category also has an optional `description` (under its title in the academy), `placeholder` (the
  academy's input placeholder) and `eligibility_criteria` (rich text from the admin's Tiptap editor,
  **sanitized to p/h2/h3/lists/links by the API on save**, shown to members in a modal).
- **`Nominees`** — the nominatable entities: **artists / bands / venues /
  songs** (+ albums); CRUD. Realized as the **`Catalog`** module (one flat submodule per type).
  Members nominate by typing a name. The ballot suggests Catalog entries after 3 letters; a suggestion
  the member chooses is linked to that entry at once (no submission, nothing to reconcile). Any other
  name is **free text**, so `Catalog/NomineeSubmission` stages each typed name and an admin
  **reconciles** it — links to an existing entry or creates a new one — into a canonical Catalog entity
  before shortlists are generated (assisted: dedup + fuzzy match suggestions; gated `nomineeSubmissions` perm).
  A mistaken link can be **unlinked** back to pending until voting opens.
- **`Academy`** — members, invitations, and **ranked nominations**: 5 nominees
  per category, **order determines points** (10·8·6·4·2). Nominations can be
  **saved and resumed** until the nomination deadline. A member need not fill every
  category or all 5 places: **at least one nominee in at least one category** is enough to submit.
  Members **type nominee names**: one chosen from the Catalog suggestions is linked directly, the rest
  are free text reconciled via `Catalog/NomineeSubmission`.
  Also handles **proposing future academy members**, which only **founding members** may do
  (`members.is_founding_member`, default false, set by an admin per member or for a whole import, never
  for a member created from a proposal; others get a 403 and the academy hides the section; 2026-10-23).
  Approving a proposal creates the member and **sends their invitation at once** (admin-added members are still invited by hand). A member has
  three optional contact details — `position`, `company`, `phone` (*funcție / firmă / telefon*) — set
  by an admin or copied from the proposal on approval; **only the admin endpoints return them**, never the member-facing ones.
  Admins can also **import members from a CSV / XLSX list** (built 2026-10-14 for the client's confirmation
  sheet): one `awaiting_invitation` member per row, columns found by header, unusable rows skipped and
  reported, nothing e-mailed. Detail: `academy.md` §1. The admin roster lists the newest
  members first; the academy ballot's categories follow their `position`.
  The invitation e-mail has **templates** (2026-10-16: `default`, plus the client's reminders `unconfirmed-romanian` and `unconfirmed-english`), listed in the API's
  `config/academy-invitations.php`; the admin's "Send invite…" modal sends a chosen one to picked
  `awaiting_invitation` or `invited` members (an invited one gets a re-send), each their own e-mail. Every other invitation uses the default; only the
  invitation changes language, not the app or later e-mails. Detail: `academy.md` §3.
- **`SpecialAwards`** — the categories that are not ranked (built 2026-10-12). Entries are **free text**
  (a title only), no Catalog link.
  - `propose_or_vote` (*Lifetime Achievement Award*, *ROMIAS's WOW*): in the nomination window a member
    may **propose one entry (it counts as their vote) and vote for one approved entry another member
    proposed**, per category (changed 2026-10-14 from "one or the other"); `special_votes.via_proposal`
    tells the two votes apart and the unique key (category, member, via_proposal) caps each at one. A
    proposal is visible to others only after an admin approves it. Admins (`specialAwards` permission)
    approve, reject with a reason, correct the text, or **merge a duplicate** into the entry that stays
    (its votes move; a member may end up with both their votes on it, and both count). Most
    member votes wins; **no public vote**; a tie is shown as a tie.
  - `appointed` (*Romanian Critics' Choice Award*): the result comes from a third party and an admin
    enters it. Members and voters never see the category before publication.
  This replaces the once-planned `CriticsChoice` module and `critic` guard, which are **not being built**.
  Detail: `domain-model.md`, `SpecialAwards`.
- **`Voting`** — public voting-link requests; one-time links; ballot where each
  category is optional but **≥1 category required** to submit; per included
  category the voter ranks **exactly 3** shortlisted nominees (1 = favourite);
  **one submission per person**, no re-request afterwards. A new address is checked with
  **ZeroBounce** before a link is issued (reject → 422; "accepted with a note" → flagged on the ballot
  for fraud review; ZeroBounce down → link sent unchecked, and the failure is logged on the API's
  `zerobounce` channel, `storage/logs/zerobounce/zerobounce-<date>.log`). Decision table: `config/voting.php`, from
  the client's `zero-bounce-scores.csv` (kept outside git); ZeroBounce client: the small `EmailValidation` module. One voter per
  mailbox ignores `+tag` and Gmail dots. Detail: `voting.md` §2.
- **`Scoring`** — ranking→points rules and results weighting (academy 10·8·6·4·2,
  public 10·8·6; default `attributed` algorithm with the 60/40 split baked into its
  ladders). Shared by `Academy` and `Voting`. Full detail:
  `scoring-results.md`.
- **`Results`** — final aggregated results. **Custodian-only** view/export
  *before* public publication. Includes the special categories (member vote tallies, or the appointed
  result); the admin review screens for special awards deliberately show no vote counts.
- **`FraudMonitoring`** — near-real-time vote monitoring, fraud indicators
  (graphs/tables), and **vote cancellation that always requires a reason**.
- **`Reporting`** — general statistics (total votes, per category, cancelled,
  etc.), **kept separate from final results**.
- **`Audit`** — audit trail for most events/actions across the subprojects.
- **`Notifications`** — e-mail notifications, on demand and scheduled, on Laravel's own notification
  system. Admins get an e-mail plus an unread item in the admin (proposal needs review, member joined,
  new fraud alerts, daily statistics), sent to the users whose permission lets them act on it. Members
  get an e-mail only (nominations opened, nomination deadline reminder). The scheduled ones follow the
  Romanian day. Needs the API's `FRONTEND_ADMIN_URL`. Detail: `notifications.md`.
- **`Auth`** (formerly named `Identity` in this map) — accounts, roles and policies; magic-link /
  Sanctum wiring. Realized as the `Auth` module (admin login + the guard-agnostic magic-link primitive),
  supported by the `Users`, `Roles` and `Permissions` modules. (OTP was dropped — magic-link only.)

---

## 4. Roles & permissions

| Role | Can do |
|------|--------|
| **Academy member** | Submit/resume ranked nominations; propose an entry and vote for another member's in each special award; propose future members (founding members only) |
| **Management / admin** | CRUD editions, categories, nominees; view reporting; review special-award proposals and enter appointed results (Critics' Choice) |
| **Fraud monitor** | Review public ballots; monitor votes; cancel votes (with reason, audited) |
| **Custodian** | View/export final, complete results before publication; **also** review/cancel votes (shares this with the fraud monitor) |

> **Implementation note (2026-09-15):** vote review/cancellation is the `fraudMonitoring` permission,
> granted to **`fraud_monitor`** and **`custodian`** only — deliberately **not** to `admin`. This is a
> considered divergence from this table's original "admin can cancel votes" line (chosen when
> `FraudMonitoring` was built): a dedicated `fraud_monitor` role owns that duty instead. See
> `access-control.md` §2h.

Every controller action is authorised via a **Policy**,
never inline gate checks.

---

## 5. Domain rules & invariants

- Exactly **one active edition** at a time.
- Academy nominations are **gated by a deadline**; saveable/resumable until then.
- Academy submission requires **at least one category with at least one nominee**; categories may stay
  empty and hold fewer than 5 (a lone pick is rank 1). A category nobody fills gets no shortlist.
- Points are derived from **rank order** (1→5); academy weight is **60%**.
- In a special (`propose_or_vote`) category a member makes **at most one proposal** (counting as their
  vote) **and casts at most one vote** for an entry another member proposed. The proposal can be withdrawn
  **only while pending**; once approved (or merged) it stands. Nothing there changes once the results are
  published.
- A public voter may submit **once**; no second link is issued after submission.
- **Every vote cancellation carries a reason** and is audited.
- Results are **custodian-gated** until the edition's results are published; publishing them ends the edition.

---

## 6. Security & GDPR

These projects fall under **GDPR** — treat personal data conservatively.

- **Hash / pseudonymize** personal data (emails, IPs) wherever plaintext is not
  strictly required. Document, per field, whether it is hashed or kept plaintext
  and **why**.
- **Anti-fraud** operates at two levels: application logic (`FraudMonitoring`)
  **and** Cloudflare custom rules.
- **Signed voting links** must be single-use and expiring; enforce both.
- **ZeroBounce is a third-party processor**: a voter's address is sent to it (EU endpoint, no IP) once,
  when a new address requests a voting link. Only its status codes are stored. The agreement with
  ZeroBounce is the client's matter.
- **Magic-link tokens**: single-use, expiring (per-type lifetimes in the API's
  `config/magic-link.php`), stored hashed, rate-limited. (OTP was dropped.)
- **Logs and failed jobs** may hold an e-mail address in plaintext (decided 2026-10-12, from the
  client's privacy policy: technical diagnosis, legitimate interest) **as long as they are deleted in
  time**. The `failed_jobs` table is pruned by the scheduler after `LOG_MAX_FILES` days (14). Log files
  are limited by **count, not age**: each daily channel keeps its newest `LOG_MAX_FILES` files and drops
  older ones only when it writes on a new day, so a channel that seldom writes (`delivery`, `zerobounce`)
  can keep files for months. There is no age-based deletion of log files.
  A **link token is never logged**: failed sends go to the `delivery` channel
  (`storage/logs/delivery/delivery-<date>.log`) with the token written as `[hidden]`. A payload factory
  that takes a token marks it `#[SensitiveParameter]`. Detail: `domain-model.md`, `Delivery`.
- **Audit** all sensitive actions — especially vote cancellations and any
  access/export of final results.
- Validate all input, keep secrets in `.env` and read them via `config()`, never
  disable CSRF.

---

## 7. Conventions & tooling

PHP/Laravel style, Modular-DDD building blocks, migrations, testing (**Pest**),
static analysis (**Larastan level 6**), **Pint** and the git workflow are in the API's
`.ai/guidelines/` (merged into its `CLAUDE.md`). Ecosystem-wide notes:

- API responses use **Resource classes**; input via **Form Requests**.
- **DTOs** cross module boundaries — never raw arrays.
- API versioning stance: **TBD** (decide before the first frontend integrates).
- Long-running work (magic-link / voting-link emails, exports) goes through **queues**.
- **User-facing error text is translated by the API, not the frontends.** Clients send
  `Accept-Language` (members/voters see Romanian); the API sets its locale from it and
  wraps every user-facing message in `__()` (English key, Romanian in `lang/ro.json`,
  rule messages in `lang/ro/validation.php`). Frontends **display the API's
  `message` / `errors` as-is** and keep their own i18n for UI text and genuine
  fallbacks only (network failure, nothing to act on). Details: `architecture.md` §7.
  The academy and voting apps follow this (the `Voting` module's messages are translated too);
  the admin app doesn't send the header yet (sees English).
- **Datetimes: the API stores and sends UTC; each frontend converts for people.** The API has no
  `APP_TIMEZONE` (decided 2026-10-07: local time in a zone-less column is ambiguous on clock-change
  nights). The admin shows and reads every datetime in a fixed **`Europe/Bucharest`**, whatever the
  device's zone (`rominas-admin/src/lib/datetime.ts`); the academy and voting apps show the
  browser's local time. Known leftover: the API's per-day reports count UTC days.
- **Project artisan commands are named `romias:*`** (`romias:detect-fraud`, `romias:daily-statistics`,
  `romias:nomination-reminders`, `romias:test-mail`), registered in the API's `bootstrap/app.php`.
- **E-mail transport:** `MAIL_DELIVERY_TRANSPORT=smtp|brevo` (default `smtp`) picks the API's SMTP server or
  Brevo's HTTP API. Each e-mail has one factory in `app/Modules/Delivery/Factories/`, which builds a plain
  `MailPayload`; the transport only picks the service that sends it. The sender name follows the
  audience: `MAIL_FROM_NAME_ADMIN` / `_ACADEMY` / `_VOTING` (defaults *Platforma ROMIAS*,
  *Academia ROMIAS*, *ROMIAS*). `php artisan romias:test-mail <address> <template|all>` sends any e-mail
  with sample data. Detail: `domain-model.md`, `Delivery`.
  Every e-mail template wraps its content in the Blade components `<x-email.layout>` and `<x-email.button>`
  (built from tables, with a VML button), so that classic Outlook renders them (2026-10-14).
- **The API's `.env.example`** lists Laravel's settings first and every project setting under a "Rominas"
  heading at the end; add a new project setting there.

---

## 8. Edition lifecycle (state model)

The `Editions` module enforces this flow:

```
draft   (admins invite academy members manually, proposed ones on approval — not a lifecycle status)
  → nominations open  ──(deadline)──▶ nominations closed
  → shortlist announced, public voting open
  → voting closed
  → committee review of results
  → results published  → edition archived
```

Only one edition is non-archived/active at a time.

---

## 9. Repos & docs

- **`rominas/`** — parent folder (not a repo); holds all subproject repos as siblings.
- **`rominas/rominas-api/`** — the Laravel API (domain + all endpoints). Its
  `CLAUDE.md` / `AGENTS.md` hold the Laravel Boost rules plus the Rominas guidelines
  (`.ai/guidelines/`); `docs/` documents every built module (start at `docs/README.md`)
  and holds this guide.
- **Frontends** (**Next.js**, each with its own `CLAUDE.md` — read it first):
  - `rominas-admin/` — the management dashboard (built: editions, categories, results,
    catalog, academy, shortlist, special awards, audit log, access control, notifications).
  - `rominas-academy/` — the academy member dashboard (built: ballot, special awards, proposals).
  - `rominas-voting/` — public voting (built: status-aware landing + link request, tap-to-rank
    ballot at `/vot`; no accounts). Needs the API's `FRONTEND_VOTING_URL` and `TRUSTED_PROXIES`.
    Production serves it at **`romias.ro/voting`** (a path of the WordPress site, not a subdomain;
    `NEXT_PUBLIC_BASE_PATH=/voting` = Next's `basePath`, routed by Caddy on Server B).
- **`rominas/rominas-deployment/`** — production hosting guide (own repo): two Hetzner Cloud
  servers behind Cloudflare, WordPress, backups, and the `@romias.ro` mail setup (README,
  "Email"). Start at its `README.md`. Never commit secrets there.
  The frontends deploy as **versioned builds**: `scripts/deploy-frontend.sh` sets `BUILD_ID` to the
  commit hash, each app's `next.config.ts` builds into `builds/build_<hash>` and uses it as Next's
  `deploymentId` (empty `BUILD_ID` = `.next`, as in development). Details: `04-web-server.md` step 7.
  `scripts/deploy-api.sh` first dumps the API database to `/home/rominas/backups/<timestamp>/` on
  Server A and stops if the dump fails; those folders are never pruned (`03-api-server.md` step 7).
- **Git workflow (since 2026-10-15):** new work in `rominas-api`, `rominas-admin`, `rominas-academy`
  and `rominas-voting` is developed and committed on the **`dev`** branch. Merging `dev` into `main`
  and pushing are left to the maintainer. `rominas-deployment` stays on `main` for now
  (commit there; the maintainer pushes).

## Open items (TBD)

- **API versioning** strategy.

(The points-per-rank formula is settled — see `scoring-results.md`.)
