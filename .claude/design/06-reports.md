# Phase 6 — Reports

Status: **not started**.

## Package

`pxlrbt/filament-excel` (thin Filament-native wrapper over `maatwebsite/excel`) for one-click Excel/CSV export straight from a Filament table's header/bulk actions.

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
