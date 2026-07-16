# Inventory Management System — Project Plan

Stack: Laravel 13 + Filament 3 + Filament Shield (roles/permissions) + Laravel Jetstream (Livewire stack, no Teams) + MySQL (via local LAMPP/XAMPP).

Status: **Phases 0–6 complete**, including the user-group / item-group ordering-permission layer (§3a, added 2026-07-14). Phase 7 (audit log) is next.

---

## 1. Confirmed decisions (from clarification round)

| Topic | Decision |
|---|---|
| Approval ceiling | Approver can approve **up to** the requested quantity, never more. Issuance can be up to the approved quantity, capped by actual stock on hand. |
| Low-stock threshold | **Global** threshold (single system setting), not per-product. Documented trade-off: a single number may over/under-alert for products with very different typical stock levels — revisit if it proves noisy in practice. |
| Stock inflow | Suppliers are **read-only**. Only Storekeeper/Admin record stock-in (purchases/deliveries) into the system. |
| Roles | `Admin`, `Approver`, `Storekeeper`, `Demander`, `Supplier` — seeded via `RoleSeeder`, permissions configurable per role through Filament Shield. `Admin` is configured as Shield's `super_admin` (bypasses all permission checks). |
| Multi-tenancy | **None.** This app is deployed fresh per organization (single-tenant per install). No `Organization` model. |
| UI | **Single Filament panel** (`/admin`) for all roles (Admin, Approver, Storekeeper, Demander, Supplier). Navigation/resources/actions are shown or hidden per role via Shield permissions — no separate Jetstream-driven demander portal. Jetstream supplies auth scaffolding (registration, profile, 2FA, password reset) that Filament's panel login reuses. |
| Reports | Daily / Monthly / Yearly reports, exported as **Excel/CSV**, plus on-screen Filament tables/widgets as the baseline view. |
| Jetstream stack | Livewire (not Inertia), Teams **disabled**. |
| Database | MySQL via LAMPP, database name `ims_v1`, root user, no password (local dev only — production `.env` will use real credentials). |
| User groups & item groups | Added 2026-07-14. **Orthogonal to both Roles and Categories** — see §3a. Admin organizes Demanders (and any users) into **User Groups**; Admin organizes Products into **Item Groups** (a separate taxonomy from Categories, which remain the browsing/search classification). A User Group is granted permission to order from specific Item Groups; a Demander can only request products reachable through *some* Item Group their User Group(s) are permitted for. Both User↔UserGroup and Product↔ItemGroup are **many-to-many**. |

---

## 2. Environment (already set up)

- PHP 8.5.4 (extensions added: `intl`, `zip`, `gd`, `bcmath`, `mbstring`)
- Composer 2.10.2, Node 24, npm 11
- MySQL/MariaDB 10.4 via `/opt/lampp` (LAMPP), reachable at `127.0.0.1:3306`
- Laravel 13.19 installed at project root
- Jetstream (Livewire, no Teams) installed and migrated
- Filament 3 admin panel installed at `/admin`
- Filament Shield installed, `spatie/laravel-permission` migrated
- `RoleSeeder` seeds the 5 roles; `DatabaseSeeder` creates an `admin@example.com` super-admin user
- `config/filament-shield.php`: `super_admin.name` set to `Admin`

Remaining packages to add during backend implementation (not yet installed):
- `spatie/laravel-activitylog` — full audit log of model changes (requests, approvals, issuances, stock movements, user/role changes)

**Dropped:** `pxlrbt/filament-excel` — couldn't install (see §5/Phase 6 build notes below): its Excel engine caps at PHP <8.5, and the PHP-8.5-compatible `filament-excel` majors need Filament 4/5, not our 3.3. Reports export via plain-PHP CSV instead (`App\Support\Reports\CsvExport`).

---

## 3. Domain model (ERD, textual)

```
Category (1) ───< Product (M)
Unit     (1) ───< Product (M)

Product (1) ───< StockMovement (M)          -- append-only ledger: in / out / adjustment
Product (1) ───< StockRequestItem (M)

User "demander"  (1) ───< StockRequest (M)
StockRequest (1) ───< StockRequestItem (M)

StockRequestItem (1) ───< RequestApproval (M)   -- append-only: every approval decision, supports re-approval trail
StockRequestItem (1) ───< StockIssuance  (M)    -- append-only: supports partial/multiple issuances per item

StockIssuance (1) ───1 StockMovement            -- every issuance creates exactly one "out" stock movement

Setting (key -> value)                          -- system_low_stock_threshold, etc.

activity_log (polymorphic, spatie/laravel-activitylog) -- causer=User, subject=any of the above
```

### Tables

**categories**
`id, name, slug (unique), description, timestamps`

**units**
`id, name (e.g. "Box"), symbol (e.g. "box"), timestamps`

**products**
`id, name, sku (unique), category_id (FK), unit_id (FK), description, current_stock (int, denormalized cache maintained by StockMovement observer), timestamps, soft deletes`

**stock_movements** (append-only ledger — source of truth for stock history; `products.current_stock` is a derived cache)
`id, product_id (FK), type (enum: in, out, adjustment), quantity (int, always positive; sign implied by type), reference_type/reference_id (nullable morphs — links to StockIssuance for "out", or a future PurchaseEntry for "in"), note, created_by (FK users), timestamps`

**stock_requests**
`id, requester_id (FK users), status (enum: pending, partially_approved, approved, rejected, partially_issued, issued, cancelled), notes, timestamps`

**stock_request_items**
`id, stock_request_id (FK), product_id (FK), requested_qty (int), approved_qty (nullable int — cumulative decided amount, ≤ requested_qty), issued_qty (int default 0 — cumulative sum of issuances, ≤ approved_qty), status (enum: pending, approved, rejected, partially_issued, issued), timestamps`

**request_approvals** (append-only trail — who approved what, when, for how much)
`id, stock_request_item_id (FK), approver_id (FK users), decision (enum: approved, rejected), approved_qty (int, 0 if rejected), remarks, timestamps`

**stock_issuances** (append-only trail — supports partial issuance across multiple storekeeper visits)
`id, stock_request_item_id (FK), storekeeper_id (FK users), issued_qty (int), remarks, timestamps`

**settings**
`id, key (unique), value, timestamps` — simple key/value store; starts with `low_stock_threshold`

**roles / permissions / model_has_roles / model_has_permissions / role_has_permissions**
Standard `spatie/laravel-permission` tables (already migrated).

**activity_log**
Standard `spatie/laravel-activitylog` table (to be migrated in the backend phase).

### Business rules enforced in the backend (not just UI)
1. `approved_qty ≤ requested_qty` — enforced in the Approval action/form, not just validated client-side.
2. `issued_qty (cumulative) ≤ approved_qty` and `≤ product.current_stock` at the moment of issuance — enforced in the Issuance action inside a DB transaction with a row lock (`lockForUpdate`) on the product to prevent race conditions between simultaneous issuances.
3. Every issuance atomically: (a) inserts a `StockIssuance` row, (b) inserts a linked `StockMovement` (type=out), (c) decrements `products.current_stock`, (d) recomputes the parent `StockRequestItem.status` and `StockRequest.status`.
4. Stock-in (Storekeeper/Admin only) atomically inserts a `StockMovement` (type=in) and increments `products.current_stock`.
5. All of the above are also captured by `spatie/laravel-activitylog` for the audit trail, in addition to the domain tables' own append-only history.

---

## 3a. User Groups & Item Groups (ordering-permission layer)

Added 2026-07-14. This is a **second, independent classification/permission system**, layered on top of what already exists — it does not replace Roles (who can do what action) or Categories (how products are browsed/searched). It answers a narrower question: *which specific products can a given Demander request?*

- **Roles** (`Admin`/`Approver`/`Storekeeper`/`Demander`/`Supplier`, via Shield) control what *actions* a user can perform in the system.
- **Categories** are the browsing/search taxonomy every user sees (Stationery, PPE, Hygiene, …) — unchanged, still what "category-wise search with pagination" (§3a below) filters by.
- **Item Groups** are a *separate* taxonomy on Products, used **only** to gate ordering permission. A product's category and its item-group(s) are independent — e.g. a product could be in category "Stationery" and item-group "Facilities-Orderable" simultaneously; the two classifications don't need to line up.
- **User Groups** are how Admin organizes users (typically Demanders) for the purpose of granting item-group ordering permission — e.g. "Facilities Team", "IT Department". Not tied to Roles: a User Group is purely about which item-groups its members may order from.

### Cardinality (confirmed)
- `User` ↔ `UserGroup`: **many-to-many**. A demander's permitted item-groups are the **union** of permitted item-groups across all their user-groups.
- `Product` ↔ `ItemGroup`: **many-to-many**. A product is orderable by a demander if *any* of the product's item-groups is in the demander's permitted set.
- `UserGroup` ↔ `ItemGroup` (the permission grant itself): **many-to-many allow-list**. Presence of a row = permitted; absence = not permitted. No separate boolean — the pivot table's existence *is* the grant, which also gives a natural, auditable "who granted this and when" via `granted_by` + timestamps on that pivot.

### Resolved defaults (not explicitly specified, chosen for sane rollout behavior — revisit if wrong)
- **Unclassified products stay open.** A product with **zero** item-groups assigned is treated as unrestricted — orderable by any Demander — rather than unorderable by everyone. Rationale: the point of this feature is to *restrict* specific classified items to specific groups, not to silently lock ordering for every product an Admin hasn't gotten around to classifying yet. Once a product is put into at least one item-group, only demanders with a permitted path to that group can order it.
- **Ungrouped demanders have no restricted-item access.** A Demander in zero user-groups can still order any *unclassified* product (per the rule above) but cannot order anything gated behind an item-group, since they have no permitted set to draw from.
- **Admin bypasses this layer entirely**, consistent with `Admin` already bypassing all Shield permission checks as `super_admin` — an Admin can always add any product to a request regardless of item-group.
- **Approvers/Storekeepers/Suppliers are unaffected** — this restriction only gates the *Demander's* product picker when creating a request (and, per the new requirement, the Demander's product browse/search view). It has no bearing on who can approve, issue, or view inventory.

### Tables

**item_groups**
`id, name, slug (unique), description, timestamps`

**user_groups**
`id, name, description, timestamps`

**item_group_product** (pivot — product's item-group memberships)
`item_group_id (FK), product_id (FK), timestamps`

**user_user_group** (pivot — user's group memberships)
`user_id (FK), user_group_id (FK), timestamps`

**item_group_user_group** (pivot — the permission grant itself; existence = allowed)
`item_group_id (FK), user_group_id (FK), granted_by (nullable FK users), timestamps`

### Backend enforcement

`StockRequest::addItem(Product $product, int $requestedQty)` is the **only sanctioned way** to add an item to a request (mirrors how stock changes only go through `recordStockIn`/`recordStockOut`). It calls `$this->requester->canOrderProduct($product)` and throws `InventoryRuleException` if not permitted — enforced in the backend, not just by hiding options in the Filament product picker. `User::canOrderProduct()` implements the bypass/union/open-if-unclassified logic above.

### Demander product browsing (new requirement, not previously in this plan)

Demanders search/browse products **category-wise, paginated** — this was already implicitly supported by a standard Filament table (category filter + search + pagination are Filament table features, not custom-built). What's new: for a Demander specifically, the product list (both the browse view and the "add item" picker on the request form) is additionally scoped to products they're permitted to order (per `canOrderProduct()` above) — Admin/Approver/Storekeeper/Supplier continue to see the full catalog.

---

## 4. Roles & permissions matrix

| Capability | Admin | Approver | Storekeeper | Demander | Supplier |
|---|:---:|:---:|:---:|:---:|:---:|
| Manage users/roles/permissions | ✅ | – | – | – | – |
| Manage categories/units | ✅ | – | – | – | – |
| Manage user-groups & item-groups (ordering permissions) | ✅ | – | – | – | – |
| View products & stock levels | ✅ | ✅ | ✅ | ✅ (scoped, see §3a) | ✅ (read-only) |
| Create/edit products | ✅ | – | – | – | – |
| Record stock-in | ✅ | – | ✅ | – | – |
| Create stock request | ✅ | – | – | ✅ (only for products their user-group(s) permit — §3a) | – |
| View own requests | ✅ | ✅ | ✅ | ✅ (own only) | – |
| Approve/reject request items | ✅ | ✅ | – | – | – |
| Issue approved items | ✅ | – | ✅ | – | – |
| View low-stock alerts | ✅ | ✅ | ✅ | – | ✅ |
| View/export reports | ✅ | ✅ | ✅ | – | – |
| View audit log | ✅ | – | – | – | – |

`Admin` = Shield `super_admin`, so it always passes every gate regardless of this table — via a real `Gate::before()` bypass (`config/filament-shield.php`'s `super_admin.define_via_gate` is set to `true`; Shield's own default of `false` instead physically attaches every permission to the Admin role's DB row at `shield:generate` time, which breaks on any fresh database that hasn't had it re-run — not what "Admin bypasses everything" should mean). All other rows are enforced via Shield-generated resource permissions plus four custom permissions seeded by `PermissionSeeder`: `approve_request`, `issue_request`, `record_stock_in`, `view_reports`.

---

## 5. Build phases (execution order)

- **Phase 0 — Environment & base install** ✅ done (this session)
- **Phase 1 — Static prototype** (next, pending your approval): plain HTML/CSS pages in `static_prototype/` for the key screens (login, dashboard per role, product catalog, request form, approval queue, issuance screen, stock alerts, reports). No backend logic — just layout/UX to agree on before wiring up Filament.
- **Phase 2 — Schema & models**: migrations + Eloquent models for Category, Unit, Product, StockMovement, StockRequest, StockRequestItem, RequestApproval, StockIssuance, Setting; model factories + tests for the business rules in §3. ✅ done, **extended** 2026-07-14 with ItemGroup, UserGroup, and their 3 pivot tables + `User::canOrderProduct()` / `StockRequest::addItem()` (§3a).
- **Phase 3 — Filament resources (CRUD)**: CategoryResource, UnitResource, ProductResource (with stock-in action), UserGroupResource (members + permitted item-groups), ItemGroupResource (products), UserResource (role + user-group assignment). ✅ done 2026-07-14 — see `.claude/design/03-filament-resources.md` for what shipped and two important bugs caught/fixed along the way (`User::canAccessPanel()` was missing entirely, and Shield's `define_via_gate: false` default made the Admin bypass fragile).
- **Phase 4 — Request workflow**: StockRequestResource with item relation manager, submit/approve/reject/issue actions, status transitions, notifications. ✅ done 2026-07-16 — see `.claude/design/04-request-workflow.md` for what shipped vs. the original sketch.
- **Phase 5 — Alerts**: low-stock Filament widget + dedicated "Stock Alerts" page, system Setting for the threshold. ✅ done 2026-07-16.
- **Phase 6 — Reports**: Daily/Monthly/Yearly report pages with date-range filters, CSV export (plain PHP — see §2). ✅ done 2026-07-16.
- **Phase 7 — Audit log**: `spatie/laravel-activitylog` wired into all domain models + a Filament page to browse/search the log.
- **Phase 8 — Search & polish**: global search across products/requests/users, table filters, UI pass, seed demo data, final permission review per role.

Each phase will be implemented service-by-service and reviewed before moving to the next, per your original instructions.

---

## 6. Folder structure

```
ims_v1/
├── PLAN.md                     <- this file
├── CLAUDE.md                   <- context for Claude Code sessions
├── .claude/
│   ├── memory/CONTEXT.md       <- running status log across sessions
│   └── design/                 <- one doc per build phase (see §5)
├── static_prototype/           <- Phase 1 static HTML/CSS mockups
├── app/                        <- Laravel app code
├── database/
│   ├── migrations/
│   └── seeders/
└── ... standard Laravel structure
```

---

## 7. Terminal commands used to reach current state

```bash
# Database
/opt/lampp/bin/mysql -u root -e "CREATE DATABASE IF NOT EXISTS ims_v1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Laravel
composer create-project laravel/laravel ims_v1

# .env: DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE=ims_v1, DB_USERNAME=root, DB_PASSWORD=

# Jetstream (Livewire stack, no Teams)
composer require laravel/jetstream
php artisan jetstream:install livewire

# Filament
composer require filament/filament:"^3.3" -W
php artisan filament:install --panels

# Shield (roles & permissions)
composer require bezhansalleh/filament-shield
php artisan vendor:publish --tag=filament-shield-config
php artisan shield:install admin
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Migrate + seed
php artisan migrate
php artisan db:seed
```

### Phase 2 — schema & models

```bash
# Enums
php artisan make:enum Enums/RequestStatus --string
php artisan make:enum Enums/RequestItemStatus --string
php artisan make:enum Enums/StockMovementType --string
php artisan make:enum Enums/ApprovalDecision --string

# Migrations (created in FK-dependency order; two had to be renamed after
# generation because make:migration gave request_approvals and
# stock_issuances timestamps that sorted before tables they depend on)
php artisan make:migration create_categories_table
php artisan make:migration create_units_table
php artisan make:migration create_products_table
php artisan make:migration create_settings_table
php artisan make:migration create_stock_requests_table
php artisan make:migration create_stock_request_items_table
php artisan make:migration create_request_approvals_table
php artisan make:migration create_stock_movements_table
php artisan make:migration create_stock_issuances_table

# Models + factories
php artisan make:model Category -f
php artisan make:model Unit -f
php artisan make:model Product -f
php artisan make:model Setting -f
php artisan make:model StockRequest -f
php artisan make:model StockRequestItem -f
php artisan make:model RequestApproval -f
php artisan make:model StockMovement -f
php artisan make:model StockIssuance -f

# Separate test database (this machine has no pdo_sqlite, so phpunit.xml
# points DB_CONNECTION=mysql / DB_DATABASE=ims_v1_testing instead of the
# usual :memory: sqlite default — see config/database.php's extra
# 'mysql_lock_test' connection too, used only by the row-lock test)
/opt/lampp/bin/mysql -u root -e "CREATE DATABASE IF NOT EXISTS ims_v1_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate:fresh --seed
php artisan test
```

### Phase 2 extension (2026-07-14) — User Groups & Item Groups (§3a)

```bash
php artisan make:migration create_item_groups_table
php artisan make:migration create_user_groups_table
php artisan make:migration create_item_group_product_table
php artisan make:migration create_user_user_group_table
php artisan make:migration create_item_group_user_group_table
# (item_group_product renamed to a later timestamp — same FK-ordering
# gotcha as Phase 2: it depends on item_groups but was generated with a
# timestamp that sorted before it)

php artisan make:model ItemGroup -f
php artisan make:model UserGroup -f

php artisan migrate:fresh --seed
php artisan test
```

### Phase 3 (2026-07-14) — Filament CRUD resources

```bash
php artisan make:filament-resource Category --generate
php artisan make:filament-resource Unit --generate
php artisan make:filament-resource ItemGroup --generate
php artisan make:filament-resource UserGroup --generate
php artisan make:filament-resource Product --generate
php artisan make:filament-resource User --generate
# (each --generate scaffold was then substantially rewritten by hand —
# auto-inferred forms exposed raw internals like two_factor_secret and
# current_team_id on User, and none of them had the item-group scoping,
# stock-in action, or auto-slug behavior this project needs)

php artisan shield:generate --panel=admin --all
# generates Policies (app/Policies/) + CRUD permission rows per resource

php artisan make:seeder PermissionSeeder
# custom action permissions (approve_request, issue_request,
# record_stock_in, view_reports) + role assignments per §4's matrix

# Correct order matters: shield:generate must run before db:seed, since
# PermissionSeeder assigns roles against permissions it expects to exist:
php artisan migrate:fresh
php artisan shield:generate --panel=admin --all
php artisan db:seed
php artisan test
```

Two bugs were caught here that would otherwise have surfaced in production, not just tests — both now documented in `CLAUDE.md` "Conventions": (1) `User` didn't implement `FilamentUser::canAccessPanel()`, which silently denies *everyone* once `APP_ENV !== 'local'`; (2) Shield's `super_admin.define_via_gate` default of `false` doesn't give Admin a real bypass, it just attaches every currently-known permission to the Admin role at `shield:generate` time — fragile and wrong for "Admin bypasses everything." Both fixed; see `.claude/memory/CONTEXT.md` for the debugging trail if this area needs touching again.

### Phase 4 (2026-07-16) — Request → approval → issuance workflow UI

```bash
php artisan make:notifications-table
# Filament's sendToDatabase() needs the standard notifications table,
# which nothing in this project had created yet

php artisan make:filament-resource StockRequest --generate
php artisan make:filament-relation-manager StockRequestResource items product
php artisan make:filament-page ViewStockRequest --resource=StockRequestResource --type=ViewRecord
# EditStockRequest (auto-generated by --generate) was deleted and never
# registered in getPages() — edits after submission go through the
# guarded approve/reject/issue actions, never a raw form

php artisan shield:generate --panel=admin --all
# generates StockRequestPolicy + CRUD permissions (view_stock::request
# etc. — same "::" naming quirk as UserGroup/ItemGroup, see CONTEXT.md)

# PermissionSeeder extended in place (not a new seeder) to grant
# view_any_stock::request / view_stock::request to Approver, Storekeeper,
# Demander, and create_stock::request to Demander only

php artisan migrate:fresh
php artisan shield:generate --panel=admin --all
php artisan db:seed
php artisan test
```

### Phase 5 (2026-07-16) — Low-stock alerts

```bash
php artisan make:filament-page Settings
php artisan make:filament-page StockAlerts
php artisan make:filament-widget LowStockWidget --table --panel=admin
# --panel=admin was required here — without it the widget generator
# hit a NonInteractiveValidationException even with --no-interaction

php artisan shield:generate --panel=admin --all
# auto-generated a widget_LowStockWidget permission even though nothing
# uses it — Shield's widget-permission generation is on by default
# (unlike pages, which need an opt-in trait), but this widget's
# visibility is actually gated by a plain custom permission check
# (view_stock_alerts) in canView(), so that generated permission just
# sits there unused. Harmless, not worth suppressing.

# PermissionSeeder extended again: added view_stock_alerts, granted to
# Approver/Storekeeper/Supplier per §4's matrix (not Demander)

php artisan migrate:fresh
php artisan shield:generate --panel=admin --all
php artisan db:seed
php artisan test
```

### Phase 6 (2026-07-16) — Reports

```bash
composer require pxlrbt/filament-excel
# FAILED — phpoffice/phpspreadsheet requires PHP <8.5, this project is on
# 8.5.4; the only filament-excel majors supporting PHP 8.5 require
# Filament 4/5. Composer reverted composer.json/.lock automatically.
# Decision (user-confirmed): plain-PHP CSV export instead, no package.

php artisan make:filament-page Reports
php artisan make:filament-widget ProductsIssuedReportWidget --table --panel=admin
php artisan make:filament-widget UserActivityReportWidget --table --panel=admin
# Reports page uses Filament\Pages\Dashboard\Concerns\HasFiltersForm +
# widgets using Filament\Widgets\Concerns\InteractsWithPageFilters — the
# same mechanism Filament's own Dashboard uses to share one filter form
# across multiple widgets, applied here to a non-Dashboard custom page

php artisan migrate:fresh
php artisan shield:generate --panel=admin --all
php artisan db:seed
php artisan test
```

Commands for the next phases will be added to this section as they're run.

---

## 8. Resolved defaults (no explicit answer given — chose the simpler/reversible option, revisit anytime)

- **Unit conversion**: not supported in v1. Each product is tracked in exactly one fixed unit (e.g. a product is either "Box" or "Piece", not convertible between the two). Simpler schema, matches "units must be fully customizable" without over-building.
- **Multi-item requests**: yes — a single `StockRequest` can contain multiple `StockRequestItem` rows (cart-like form), as already reflected in the ERD in §3.
- **Low-stock threshold default**: placeholder value of `10`, stored in `settings.low_stock_threshold`, editable by Admin in Phase 5. Change anytime after seeding.
- **Rejected items**: terminal — not resubmittable in place. The Demander creates a new `StockRequest` if they still need the item. Keeps status transitions one-directional and simple to audit; can add resubmission later if it proves too rigid in practice.
