# Inventory Management System — Project Plan

Stack: Laravel 13 + Filament 3 + Filament Shield (roles/permissions) + Laravel Jetstream (Livewire stack, no Teams) + MySQL (via local LAMPP/XAMPP).

Status: **Phase 0 (environment + base install) complete.** Phase 1 (static prototype) is next and requires your sign-off on this document first.

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
- `pxlrbt/filament-excel` — one-click Excel/CSV export straight from Filament tables (built on `maatwebsite/excel`), used for the Daily/Monthly/Yearly reports

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

## 4. Roles & permissions matrix

| Capability | Admin | Approver | Storekeeper | Demander | Supplier |
|---|:---:|:---:|:---:|:---:|:---:|
| Manage users/roles/permissions | ✅ | – | – | – | – |
| Manage categories/units | ✅ | – | – | – | – |
| View products & stock levels | ✅ | ✅ | ✅ | ✅ | ✅ (read-only) |
| Create/edit products | ✅ | – | – | – | – |
| Record stock-in | ✅ | – | ✅ | – | – |
| Create stock request | ✅ | – | – | ✅ | – |
| View own requests | ✅ | ✅ | ✅ | ✅ (own only) | – |
| Approve/reject request items | ✅ | ✅ | – | – | – |
| Issue approved items | ✅ | – | ✅ | – | – |
| View low-stock alerts | ✅ | ✅ | ✅ | – | ✅ |
| View/export reports | ✅ | ✅ | ✅ | – | – |
| View audit log | ✅ | – | – | – | – |

`Admin` = Shield `super_admin`, so it always passes every gate regardless of this table. All other rows are enforced via Shield-generated resource permissions plus four custom permissions: `approve_request`, `issue_request`, `record_stock_in`, `view_reports`.

---

## 5. Build phases (execution order)

- **Phase 0 — Environment & base install** ✅ done (this session)
- **Phase 1 — Static prototype** (next, pending your approval): plain HTML/CSS pages in `static_prototype/` for the key screens (login, dashboard per role, product catalog, request form, approval queue, issuance screen, stock alerts, reports). No backend logic — just layout/UX to agree on before wiring up Filament.
- **Phase 2 — Schema & models**: migrations + Eloquent models for Category, Unit, Product, StockMovement, StockRequest, StockRequestItem, RequestApproval, StockIssuance, Setting; model factories + tests for the business rules in §3.
- **Phase 3 — Filament resources (CRUD)**: CategoryResource, UnitResource, ProductResource (with stock-in action), UserResource (role assignment).
- **Phase 4 — Request workflow**: StockRequestResource with item relation manager, submit/approve/reject/issue actions, status transitions, notifications.
- **Phase 5 — Alerts**: low-stock Filament widget + dedicated "Stock Alerts" page, system Setting for the threshold.
- **Phase 6 — Reports**: Daily/Monthly/Yearly report pages with date-range filters, Excel/CSV export via `pxlrbt/filament-excel`.
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

Commands for the next phases will be added to this section as they're run.

---

## 8. Open items / things to confirm before Phase 2

- Do you want unit **conversion** support (e.g. 1 box = 12 pieces), or is each product tracked in exactly one fixed unit? Current plan assumes the latter (simpler).
- Should Demanders be able to request **multiple products in one request** (a cart-like form), or one product per request? Current plan assumes multiple items per request (via `stock_request_items`), matching "trackable request → approval → issuance" language.
- Any specific low-stock threshold **default value**, or should it just start at a placeholder (e.g. 10) that Admin edits in Settings?
- Should rejected request items be resubmittable, or does the Demander have to create a brand-new request?

These don't block Phase 1 (static prototype) — we can settle them alongside it.
