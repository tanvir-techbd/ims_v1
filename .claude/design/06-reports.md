# Phase 6 — Reports

Status: **done** (2026-07-16), with one deviation from this doc's original plan — see `.claude/memory/CONTEXT.md` for the full reasoning.

## Package — changed from the original plan

`pxlrbt/filament-excel` **could not be installed**: its Excel engine (`maatwebsite/excel` → `phpoffice/phpspreadsheet`) caps at PHP <8.5, and the only `filament-excel` majors that do support PHP 8.5 require Filament 4/5 (this project is on Filament 3.3). User confirmed: ship plain-PHP CSV export instead of blocking on a PHP downgrade. See `App\Support\Reports\CsvExport` — a small `fputcsv`-based streamer, no package. Revisit with the real package once the ecosystem catches up.

## Pages

One "Reports" page (or three — Daily/Monthly/Yearly as tabs of the same page, preferred to avoid duplicating the table) with:
- Date-range filter (default: today / this month / this year depending on tab)
- Table: product, category, total qty issued in range, number of distinct requests, last issued at
- A second table/tab: user activity — user, action count (requests created / approvals made / issuances made) in range, useful for the "detailed user activity records" requirement
- Export action on both tables (Excel + CSV)

## Data source

Aggregate from `stock_issuances` joined to `stock_request_items` -> `products`, and from `request_approvals` / `stock_issuances` grouped by actor for the activity table. Use query scopes, not raw DB queries scattered in the page class, so this stays testable.

## Acceptance criteria

- Switching date range updates both the table and what gets exported (export respects current filters, not the whole table).
- Exported file opens cleanly in Excel/LibreOffice with correct column headers.
