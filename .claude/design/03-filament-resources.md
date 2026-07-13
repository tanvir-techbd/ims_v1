# Phase 3 — Filament CRUD Resources

Status: **not started**.

## Resources

- `CategoryResource` — name, slug (auto from name), description. Simple, Admin-only.
- `UnitResource` — name, symbol. Simple, Admin-only.
- `ProductResource` — table: name, sku, category, unit, current_stock (with low-stock badge color), searchable/filterable by category and name/sku. Form: name, sku, category select, unit select, description. Header action: "Stock In" (opens a form to add a `StockMovement` of type `in`, Storekeeper/Admin only via `record_stock_in` permission).
- `UserResource` — standard Jetstream user fields + a role-select tab (Shield ships a good default for this, verify it matches our 5 roles).

## After creating each resource

Run `php artisan shield:generate --panel=admin --resource=ProductResource` (or `--all`) so Shield creates the CRUD permissions, then assign them to the appropriate roles per the matrix in `PLAN.md` §4 (either via a seeder or manually in the Roles UI — prefer a seeder so it's reproducible).

## Acceptance criteria

- Each role sees only the resources/actions the permissions matrix grants them.
- Product list search works on name and SKU.
- Category/Unit are usable as select dropdowns when creating a Product.
