# Phase 7 — Audit Log

Status: **done** (2026-07-16). See `.claude/memory/CONTEXT.md` for what actually shipped, including the `attribute_changes` data shape (this package version splits it from `properties`, differently from older docs/tutorials you might find) and the deliberate exclusions (`Product.current_stock`, `User.password`/2FA fields).

## Package

`spatie/laravel-activitylog`. Add `LogsActivity` trait + `getActivitylogOptions()` to every domain model (Product, Category, Unit, StockRequest, StockRequestItem, RequestApproval, StockIssuance, StockMovement, User/roles). Log field diffs for updates, not just "updated".

## Filament page

"Audit Log" page (Admin-only per the permissions matrix), searchable/filterable by: causer (user), subject type, date range, event type. This is in addition to — not a replacement for — the domain-level append-only trail already in `request_approvals`/`stock_issuances`/`stock_movements`, which is the primary source for the request-detail "trail" view built in Phase 4. The activity log is the general-purpose safety net (e.g. catches direct edits to a Product's description that the domain tables don't otherwise track).

## Acceptance criteria

- Editing any tracked model produces a readable log entry with old/new values for changed fields.
- Log is searchable by user and by date range from the Filament UI.
