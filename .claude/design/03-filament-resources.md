# Phase 3 — Filament CRUD Resources

Status: **in progress** (2026-07-14).

## Resources

- `CategoryResource` — name, slug (auto from name), description. Simple, Admin-only.
- `UnitResource` — name, symbol. Simple, Admin-only.
- `ProductResource` — table: name, sku, category, unit, current_stock (with low-stock badge color), searchable/filterable by category and name/sku. Form: name, sku, category select, unit select, description, item-groups (multi-select — §3a in PLAN.md). Header action: "Stock In" (opens a form to add a `StockMovement` of type `in`, Storekeeper/Admin only via `record_stock_in` permission).
- `ItemGroupResource` (new, §3a) — name, slug, description, plus a products multi-select/relation manager. Admin-only. Distinct from `CategoryResource` — do not merge these, they classify products for different purposes (browsing vs. ordering permission).
- `UserGroupResource` (new, §3a) — name, description, plus a users multi-select and a permitted-item-groups multi-select (the `item_group_user_group` grant). Admin-only.
- `UserResource` — standard Jetstream user fields + a role-select tab (Shield ships a good default for this, verify it matches our 5 roles) + a user-groups multi-select tab (new, §3a — relevant for Demanders, harmless on other roles since it's simply unused for them).

## After creating each resource

Run `php artisan shield:generate --panel=admin --resource=ProductResource` (or `--all`) so Shield creates the CRUD permissions, then assign them to the appropriate roles per the matrix in `PLAN.md` §4 (either via a seeder or manually in the Roles UI — prefer a seeder so it's reproducible).

## Demander-scoped product visibility (§3a)

`ProductResource::getEloquentQuery()` (or a dedicated table query modification) must scope results for the `Demander` role to only products passing `auth()->user()->canOrderProduct($product)` — in practice this means filtering to products with no item-groups OR at least one item-group in the demander's permitted set, expressed as a query rather than a per-row PHP filter so pagination numbers stay correct. Admin/Approver/Storekeeper/Supplier see the unfiltered catalog.

## Acceptance criteria

- Each role sees only the resources/actions the permissions matrix grants them.
- Product list search works on name and SKU, filterable by category, paginated (native Filament table behavior).
- Category/Unit are usable as select dropdowns when creating a Product; Item Groups as a multi-select.
- A Demander logged in only sees products they're permitted to order (per §3a's rules), verified by a Filament/HTTP test, not just manual click-through.
- Creating/editing a `UserGroup` can assign both member users and permitted item-groups from the same form.
