# Phase 2 — Schema & Models

Status: **done** (2026-07-14). Full table definitions and business rules are in `PLAN.md` §3 — this doc was the build checklist, not a re-explanation. See `.claude/memory/CONTEXT.md` for what actually landed and any deviations from the plan below.

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

- `Product`: `current_stock` recomputation happens via `recordStockIn()` / `recordStockOut()` — both wrap in `DB::transaction()` + `lockForUpdate()` and are the only sanctioned way to change `current_stock`. (Named differently from the `applyStockMovement()` sketched here originally — two direction-specific methods read clearer at call sites than one method plus a type flag. `adjustment` stays defined in the `StockMovementType` enum for a future manual-correction feature but has no dedicated method yet — deliberately not building that until something in Phase 3+ actually needs it.)
- `StockRequest`: `RequestStatus` cast, `recomputeStatus()` derived from its items' statuses, plus a `cancel()` guard (only while still `Pending`).
- `StockRequestItem`: `approve()`, `reject()`, `issue()` are the only way to change approval/issuance state — each guards its own invariant (approved ≤ requested, can't reduce approved below what's already issued, can't reject after any issuance, issue ≤ remaining-approved) and throws `App\Exceptions\InventoryRuleException` on violation, a single exception type whose message is meant to be shown directly to the user.
- Enums as PHP backed enums in `app/Enums/`: `RequestStatus`, `RequestItemStatus`, `StockMovementType`, `ApprovalDecision`. (`php artisan make:enum Enums/Foo` inconsistently nested some of these under `app/Enums/Enums/` — worth knowing if generating more enums later, check where the file actually landed.)

## Acceptance criteria before moving to Phase 3 — all met

- `php artisan migrate:fresh --seed` runs clean against MySQL.
- Factories exist for every model above with sensible defaults.
- Feature tests (`tests/Feature/InventoryWorkflowTest.php`, 9 tests) cover: approval cannot exceed requested qty; issuance cannot exceed approved qty; issuance is capped by actual stock and item goes `partially_issued`; full happy path through to `issued`; rejecting an item after it's had stock issued is blocked; sequential issuances never oversell; stock-in increases `current_stock` and logs a movement.
- Concurrency: `tests/Feature/ConcurrentIssuanceLockTest.php` proves `lockForUpdate()` is a real MySQL row lock (a second live connection holding the lock causes the main connection's `issue()` call to fail with a lock-wait-timeout rather than silently overselling). This test intentionally skips `RefreshDatabase` — see the class docblock for why — and cleans up its own rows in `tearDown()`.

## Addendum (2026-07-14): User Groups & Item Groups

New requirement, added after Phase 2 was otherwise complete — full rationale and resolved defaults are in `PLAN.md` §3a, not repeated here.

Additional migrations (both many-to-many, so no new columns on `products`/`users` — pivot tables only):
- `item_groups` (`id, name, slug, description, timestamps`)
- `user_groups` (`id, name, description, timestamps`)
- `item_group_product` (pivot)
- `user_user_group` (pivot)
- `item_group_user_group` (pivot — the permission grant; existence = allowed; has `granted_by` + timestamps for auditability)

Additional models: `ItemGroup`, `UserGroup`. Additional relations: `Product::itemGroups()`, `User::userGroups()`. Additional methods: `User::canOrderProduct(Product $product): bool` (bypass for Admin, union-of-groups check, open if product has zero item-groups) and `StockRequest::addItem(Product $product, int $requestedQty): StockRequestItem` — the sanctioned, permission-checked way to add an item to a request, mirroring the `recordStockIn`/`recordStockOut` / `approve`/`reject`/`issue` pattern already established.

Factories: `ItemGroupFactory`, `UserGroupFactory`. Tests: `tests/Feature/ItemGroupOrderingPermissionTest.php` covering the union-across-groups rule, the open-if-unclassified default, the Admin bypass, and the `addItem()` guard throwing `InventoryRuleException` for a denied product.
