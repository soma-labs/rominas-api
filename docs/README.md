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
| [Academy & nominations](academy.md) | Members and their `member` guard, magic-link sign-in and invitations, ranked nominations (window, save/resume, submit-all), member proposals, and the per-category shortlist that feeds public voting. |
| [Nominee reconciliation](nominee-reconciliation.md) | Turning free-text nominee names into canonical Catalog entries: capture + dedup, link / create / reject, match suggestions, and the shortlist interlock. |
| [Public voting](voting.md) | Accountless voting: one link per email, token-gated ballot (alphabetical), exactly-three-picks submit, and voter pseudonymization. |
| [Scoring & results](scoring-results.md) | The points curves, the tally, the `attributed` and `share` algorithms, caching, custodian review/export, the publish-time snapshot and the public results API. |
| [Fraud monitoring](fraud-monitoring.md) | Ballot review, reason-required cancellation batches, the scheduled `fraud:detect` detectors, severity and alert triage/dedup. |
| [Reporting](reporting.md) | The report contract and registry, JSON / CSV / Excel output, the four reports and how to add one. |
| [Audit trail](audit.md) | The append-only `AuditLog`: the `RecordAuditTrail` middleware, route-name coverage rules (opt-out `/admin`, opt-in elsewhere), actor/subject resolution, redaction + email hashing, the `Context` enrichment hook, the read API, and a checklist for new routes. |

## Planned chapters

- **Critics' Choice** — the `CriticsChoice` module (the `critic` guard and committee submissions) is not
  built yet; it gets a chapter when it is.

## Conventions

- Keep each chapter focused on **one** area; link between chapters rather than duplicating.
- When you add or change an entity, update [`domain-model.md`](domain-model.md) in the same change.
- Diagrams use [Mermaid](https://mermaid.js.org/) fenced blocks, which render on GitHub.
