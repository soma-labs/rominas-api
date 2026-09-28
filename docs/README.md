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

Not written yet — add them as the corresponding modules are built:

- **Academy & nominations** — the `Member` model + guard, the guard-agnostic magic-link/OTP
  mechanism, ranked nominations (save/resume, deadline, all-categories), and the nominee-shortlist
  bridge to public voting.
- **Voting** — accountless public voting: single-use signed links, the multi-step ballot, one
  submission per person.
- **Scoring & results** — ranking→points, the academy 60 / public 40 weighting, and the
  custodian-gated results/export.
- **Fraud monitoring** — near-real-time vote monitoring and reason-required, audited cancellation.

## Conventions

- Keep each chapter focused on **one** area; link between chapters rather than duplicating.
- When you add or change an entity, update [`domain-model.md`](domain-model.md) in the same change.
- Diagrams use [Mermaid](https://mermaid.js.org/) fenced blocks, which render on GitHub.
