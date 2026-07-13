# Phase 2 — Schema & Models

Status: **not started**. Full table definitions and business rules are in `PLAN.md` §3 — this doc is the build checklist, not a re-explanation.

## Migrations to create (in this order, for FK dependency reasons)

1. `categories`
2. `units`
3. `products` (FK: category_id, unit_id)
4. `settings`
5. `stock_requests` (FK: requester_id -> users)
6. `stock_request_items` (FK: stock_request_id, product_id)
7. `request_approvals` (FK: stock_request_item_id, approver_id -> users)
8. `stock_movements` (FK: product_id, created_by -> users, nullable morphs to reference)
9. `stock_issuances` (FK: stock_request_item_id, storekeeper_id -> users) — create after `stock_movements` since an issuance links to one

## Models + what each needs beyond the FKs

- `Product`: `current_stock` recomputation must happen via a model method (`applyStockMovement()`) called from the StockMovement creation flow — never set `current_stock` directly elsewhere.
- `StockRequest`: status enum, `recomputeStatus()` derived from its items' statuses (call after any item changes).
- `StockRequestItem`: enforce `approved_qty <= requested_qty` and `issued_qty <= approved_qty` as model-level guards (e.g. custom exceptions), not just DB constraints — the Filament actions must catch and surface these clearly.
- Enums as PHP backed enums in `app/Enums/`: `RequestStatus`, `RequestItemStatus`, `StockMovementType`, `ApprovalDecision`.

## Acceptance criteria before moving to Phase 3

- `php artisan migrate:fresh --seed` runs clean.
- Factories exist for every model above with sensible defaults.
- Feature tests cover: approval cannot exceed requested qty; issuance cannot exceed approved qty; issuance cannot exceed current stock; concurrent issuance attempts don't oversell (row-lock test).
