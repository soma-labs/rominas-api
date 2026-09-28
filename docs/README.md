# Rominas API — Documentation

Chapter-based documentation for the `rominas-api` backend. Each chapter is a standalone Markdown
file in this folder. For a high-level project overview see the [root README](../README.md); for the
ecosystem architecture decisions, locked choices and open questions see the ecosystem
[`CLAUDE.md`](../../CLAUDE.md) (one level up, in the `rominas/` parent).

## Chapters

| Chapter | Contents |
| --- | --- |
| [Domain model](domain-model.md) | Every persistent entity, per module, with fields and relationships (ER diagrams included). |
| [Module architecture](architecture.md) | The Modular-DDD layout: PSR-4 module system, per-entity skeleton, centralized wiring, how to add a module, ide-helper + tooling. |
| [Access control & auth](access-control.md) | Sanctum token flow, guards per population, spatie roles/permissions, the wildcard-permission scheme + permission-target encoding, the super_admin bypass. |
| [Edition lifecycle](edition-lifecycle.md) | The eight-state `EditionStatus` machine + guarded transitions, the six-datetime timeline (strict ordering), and the one-active-edition invariant. |
| [Audit trail](audit.md) | The append-only `AuditLog`: the `RecordAuditTrail` middleware, route-name coverage rules (opt-out `/admin`, opt-in elsewhere), actor/subject resolution, redaction + email hashing, the `Context` enrichment hook, the read API, and a checklist for new routes. |

## Planned chapters

The modules below are built, but not yet split out into chapters of their own. Until then their entities
are documented in [domain-model.md](domain-model.md) and their endpoints and authorization in
[access-control.md](access-control.md):

- **Academy & nominations** — the `Member` model + guard, magic-link sign-in and invitations, ranked
  nominations (save/resume, deadline, all-categories), member proposals, and the nominee shortlist that
  bridges to public voting. See [Academy](domain-model.md#academy) and access-control §2b–2e.
- **Nominee reconciliation** — free-text nominee submissions reconciled into canonical Catalog entries.
  See [NomineeSubmission](domain-model.md#nomineesubmission-free-text-reconciliation) and access-control §2k.
- **Voting** — accountless public voting: single-use signed links, the multi-step ballot, one
  submission per person. See [Voting](domain-model.md#voting) and access-control §2f.
- **Scoring & results** — rank→points curves, the pluggable scoring algorithm and academy/public
  weighting, the custodian-gated results/export and the publish-time snapshot. See
  [Results](domain-model.md#results), the `Scoring` notes under
  [behavioural modules](domain-model.md#behavioural-modules-no-persistent-entities), and access-control §2g.
- **Fraud monitoring** — ballot review, reason-required cancellation batches, and the scheduled
  `fraud:detect` alerts. See [FraudMonitoring](domain-model.md#fraudmonitoring) and access-control §2h.
- **Reporting** — the report registry and CSV / Excel exporters. See the `Reporting` notes under
  [behavioural modules](domain-model.md#behavioural-modules-no-persistent-entities) and access-control §2j.

`CriticsChoice` is not built yet; it gets a chapter when it is.

## Conventions

- Keep each chapter focused on **one** area; link between chapters rather than duplicating.
- When you add or change an entity, update [`domain-model.md`](domain-model.md) in the same change.
- Diagrams use [Mermaid](https://mermaid.js.org/) fenced blocks, which render on GitHub.
