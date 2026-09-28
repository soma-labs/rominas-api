# Reporting

General management statistics: how many people voted, per category and per day, how the academy
nominated, and how many votes were cancelled. Reporting is **kept separate from final results**. It never
ranks winners (that is [Results](scoring-results.md)), and it holds no custodian-only data, so `admin` can
use it.

Everything here lives in `app/Modules/Reporting/`. The module is **read-only** and stores nothing: no
table, no model. It only aggregates what the other modules have recorded. The endpoint summary is in
[access-control.md §2j](access-control.md#2j-reporting-the-reporting-module).

## 1. The report contract

Every report implements `Reports/ReportInterface`:

| Method | Purpose |
| --- | --- |
| `key()` | Stable machine key, used in the URL and the registry (e.g. `votes-per-category-per-day`) |
| `title()` | Human-readable title |
| `columns()` | Ordered column keys: the JSON table header and the export's header row |
| `rows(ReportParameters)` | The data: an `iterable` of rows keyed by column |

The metadata methods take no parameters, so the catalogue can list reports without running them. `rows()`
returns an `iterable` so a large report can be streamed straight into an export without loading everything
into memory. **One implementation serves every output format**: the JSON view and both download formats
all consume the same `columns()` / `rows()`.

`ReportParameters` carries the **edition** and an optional **`from` / `to`** window.

## 2. Registry

Reports are listed by class in **`config/reporting.php`** (`reports`). `Support/ReportRegistry` builds them
through the container (so constructor dependencies are injected) and indexes them by `key()`. To publish a
report, add its class there; to retire one, remove it.

## 3. Endpoints

All under `/api/admin/reports`, Sanctum-guarded, behind the **`reporting`** permission (held by `admin`;
`super_admin` bypasses). The module has no entity and therefore no policy, so the check is the route's
**modelless** `can:reporting` middleware.

| Endpoint | Returns |
| --- | --- |
| `GET /api/admin/reports` | The catalogue: each report's `key`, `title`, `columns` (no data) |
| `GET /api/admin/reports/{report}` | The report as tabular JSON: `key`, `title`, `columns`, `rows` |
| `GET /api/admin/reports/{report}/export?format=csv\|xlsx` | A streamed download named `{key}.{format}`; CSV by default |

An unknown key is a **404**.

**Query parameters** (shared by view and export, validated by `RunReportRequest`):

| Param | Meaning |
| --- | --- |
| `edition_id` | The edition to report on. Defaults to the **active edition**; a 422 on `edition_id` if none is active and none is given |
| `from`, `to` | Optional inclusive bounds on the relevant `submitted_at`; `to` must be ≥ `from` |

`from` / `to` are compared as timestamps. A bare date includes the whole day: `from=2026-10-05` starts
at that day's midnight, as expected, and `to=2026-10-05` now also includes the whole day — it means
`2026-10-05 23:59:59.999999`, not midnight at the start. Pass a full timestamp for a narrower bound.

## 4. Exports

`Exporters/SpreadsheetReportExporter` writes **CSV and Excel through one code path** using **openspout**: it
writes the `columns()` header, then streams `rows()` row by row. Memory use stays low regardless of report
size. `ReportFormat` supplies the file extension and content type.

## 5. The reports

All dates are **UTC days** of the stored `submitted_at`. Every public-vote report counts only **submitted**
ballots and, apart from the cancellation report, excludes **cancelled** ones. Academy reports count only
**submitted** nominations and **resolved** picks (a rejected free-text pick counts for nothing).

| Key | Columns | What it counts |
| --- | --- | --- |
| `votes-per-category-per-day` | category, date, votes | **Distinct voters** per category per day: `COUNT(DISTINCT ballot_id)`, so a voter ranking three nominees counts once |
| `nominations-per-entity` | category, entity_type, entity, nominations | How many academy picks each entity received per category, highest first. "Nominations" and "academy votes" are the same metric |
| `public-votes-per-entity` | category, entity_type, entity, points | **Rank-weighted** public points per entity per category, using the `PublicRankPoints` curve (10 / 8 / 6) |
| `cancelled-votes-per-day` | date, cancelled | Cancelled ballots per day, bucketed on the day the vote was **cast** (not cancelled), to line up with the votes-per-day report |

The per-entity reports add category and entity names with `Support/EntityLabeler`, which runs one batched
query per nominee type (no N+1).

The **final ranking** is deliberately **not** a report here: it is served by
[Results](scoring-results.md#5-custodian-review) under the custodian-only `results` permission.

## 6. Adding a report

1. Create a class in `Reports/` implementing `ReportInterface`, with a unique kebab-case `key()`.
2. Build `rows()` from query builders and scopes the owning modules already expose, such as `Ballot::valid()`
   and `NominationRanking::resolved()`, so the exclusion rules stay consistent. Apply the `from` / `to`
   window if the data has a date.
3. Use `EntityLabeler` if rows reference Catalog entities.
4. Register the class in `config/reporting.php`. It then appears in the catalogue and gets JSON, CSV and
   XLSX output with no further code.

## 7. Files

| Concern | Where |
| --- | --- |
| Contract + reports | `Reports/` |
| Registry | `Support/ReportRegistry.php`, `config/reporting.php` |
| Parameters | `DataTransferObjects/ReportParameters.php`, `Factories/ReportParametersFactory.php`, `Requests/RunReportRequest.php` |
| Export | `Exporters/SpreadsheetReportExporter.php`, `Enums/ReportFormat.php` |
| HTTP | `Controllers/ReportsController.php`, `Resources/ReportResource.php`, `routes/api/admin/reporting.php` |
