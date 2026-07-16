# Phase 5 — Low-Stock Alerts

Status: **done** (2026-07-16), except the optional stretch notification (not built — see `.claude/memory/CONTEXT.md` for why). See that file for what actually shipped.

## Setting

`settings` row `low_stock_threshold` (int), editable via a simple Filament custom page ("System Settings", Admin-only). Default value: TBD — see `PLAN.md` §8 open question, pick a placeholder (e.g. 10) if not answered by the time this phase starts.

## Surfacing the alert

- Dashboard widget (`LowStockWidget`, table widget) showing all products where `current_stock <= settings.low_stock_threshold`, visible to Admin, Approver, Storekeeper, Supplier per the permissions matrix.
- Dedicated "Stock Alerts" nav page (not just a dashboard widget) so it's easy to find, per the requirement "trigger an alert or display it in a dedicated alert list."
- Optional stretch: a Filament database notification pushed to Admin + Storekeeper the moment a `StockMovement` (type=out) causes a product to cross the threshold (listen on the movement-created event, compare before/after stock).

## Acceptance criteria

- Changing the global threshold immediately changes which products appear on the alerts page (no caching bug).
- A product exactly at the threshold is included (`<=`, not `<`).
