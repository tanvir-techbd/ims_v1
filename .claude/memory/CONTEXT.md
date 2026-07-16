# Project Context Log

Running log of decisions and status across sessions. Newest entry on top. See `PLAN.md` for the stable architecture reference — this file is for things that change session to session.

**If resuming after an interruption:** check the top entry below for "Not done yet" / "In progress" items, then check `git log --oneline` in the project root — every meaningful chunk of work is committed as it's finished, so the commit history plus this file together tell you exactly where things stand. Also check the TODO list state if the harness surfaces one.

---

## 2026-07-16 — Session 1 (continued): Phase 6 (reports) COMPLETE

**Environment wall hit, resolved with the user's input:** `pxlrbt/filament-excel` cannot install on this project's stack — its Excel engine `phpoffice/phpspreadsheet` requires PHP <8.5 (we're on 8.5.4), and the only `filament-excel` major versions that *do* support PHP 8.5 require Filament ^4.0/^5.0 (this project is pinned to Filament ^3.3). Asked the user; they chose plain-PHP CSV export over downgrading PHP system-wide. Built `App\Support\Reports\CsvExport` — a ~20-line `fputcsv`-based `StreamedResponse` helper, zero dependencies. If the ecosystem catches up (phpspreadsheet supporting 8.5, or this project upgrading to Filament 4 later), swapping in real `.xlsx` export should be a small, contained change — the export button/action shape already exists, only the implementation behind it would change.

**Built:** `App\Filament\Pages\Reports` — one page, a shared Daily/Monthly/Yearly + reference-date filter, driving two report widgets (`ProductsIssuedReportWidget`, `UserActivityReportWidget`) via Filament's *dashboard* filter-sharing mechanism (`Filament\Pages\Dashboard\Concerns\HasFiltersForm` + `Filament\Widgets\Concerns\InteractsWithPageFilters`) — normally used by the built-in Dashboard page, works identically on any custom page. Worth knowing if building another filtered-multi-widget page later: this is the reusable pattern, not something bespoke to reports.

**Query layer:** `App\Support\Reports\ReportPeriod` (period + reference date → `[from, to]` `CarbonImmutable` range — daily/monthly/yearly all resolve through one function, tested explicitly for each), `ProductsIssuedReport` (aggregates `stock_issuances` joined through `stock_request_items` → `products`/`categories`, grouped by product — `SUM(issued_qty)`, `COUNT(DISTINCT stock_request_id)`, `MAX(created_at)`), `UserActivityReport` (three `withCount()` subqueries on `User`'s existing `stockRequests()`/`approvalsMade()`/`issuancesMade()` relations from Phase 2, `HAVING` at least one count > 0 so inactive users don't pad the report). Both are plain static-method classes returning an `Eloquent\Builder`, not raw DB queries scattered in the page/widget — same class also owns its CSV header row and per-row CSV mapping, so the on-screen table and the export can never drift out of sync with each other.

**Note on `ProductsIssuedReport`:** the base query is `StockIssuance::query()` with joins, `selectRaw()`ing `products.id as id` — so Filament ends up hydrating "StockIssuance" model instances that are really per-product aggregate rows wearing the wrong model's clothes. This works fine (Filament tables just need any object with the selected attributes and a unique `id` for row keys) but is worth remembering if this ever gets refactored — it's intentionally a little unusual, not a mistake.

**Tests:** `tests/Feature/ReportsTest.php` (8 tests) — `ReportPeriod` boundary correctness for all three periods, aggregation accuracy (sums across multiple issuances of the same product, excludes out-of-range issuances, counts distinct requests correctly), `UserActivityReport` correctly attributes each action type to the right user and omits inactive ones, page-level access control (Approver in, Demander forbidden), a widget actually renders the right numbers reactively from `filters`, and — using Livewire's built-in `assertFileDownloaded()` helper, not a hand-rolled response inspection — that the CSV export actually triggers a download with the expected filename. Full suite: 75 tests, 68 passed, 7 pre-existing Jetstream skips, 0 failures.

**Not done yet:** Phase 7 (`spatie/laravel-activitylog` — general audit log across all models, distinct from the domain-level append-only trail already in `request_approvals`/`stock_issuances`/`stock_movements`).

---

## 2026-07-16 — Session 1 (continued): Phase 5 (low-stock alerts) COMPLETE

**Built:** `App\Filament\Pages\StockAlerts` (dedicated nav page, full searchable/paginated/sortable table of products at or below the threshold), `App\Filament\Widgets\LowStockWidget` (compact dashboard version, unpaginated, top of the "Access Control"... actually dashboard-level, not nav-grouped since widgets don't take a navigation group), and `App\Filament\Pages\Settings` (single numeric field for `low_stock_threshold`, the only system setting that exists right now — deliberately a lightweight custom page rather than a full Settings resource, revisit only if more settings get added later).

**Permission model:** both the widget and the page are gated by a plain custom permission `view_stock_alerts` (`Auth::user()->can('view_stock_alerts')` in `canView()`/`canAccess()`), granted to Approver/Storekeeper/Supplier per `PLAN.md` §4's matrix — explicitly *not* Demander (matrix has "–" there). `Settings::canAccess()` uses a direct `hasRole('Admin')` check instead of a permission, since nothing else will ever be granted access to it and a dedicated permission would be pure ceremony.

**Shield quirk noticed, not acted on:** `shield:generate` auto-creates a `widget_LowStockWidget` permission for any discovered widget (widget-permission generation is on by default, unlike pages which need an opt-in `HasPageShield` trait) — but since `LowStockWidget` doesn't use that trait and is gated by the custom `view_stock_alerts` check instead, that generated permission is just dead weight sitting in the `permissions` table. Harmless; not worth the config surgery to suppress it.

**Skipped deliberately:** the "optional stretch" from the design doc — a database notification pushed to Admin/Storekeeper the instant a stock movement crosses the threshold. It's explicitly optional in the design doc and nothing in the original requirements asked for push-on-cross behavior specifically (just "trigger an alert or display it in a dedicated alert list," which the page + widget satisfy). Not built; revisit only if asked.

**Tests:** `tests/Feature/StockAlertsAndSettingsTest.php` (6 tests) — alerts page shows/hides products correctly at the exact threshold boundary (`<=` not `<`, tested explicitly), changing the threshold live-updates what appears (no caching bug), Demander is forbidden from the alerts page, Admin can update the threshold, non-Admin is forbidden from Settings, and the dashboard actually renders for a non-admin permitted role (not just Admin, whose Gate bypass could otherwise mask a real widget bug). Full suite: 67 tests, 60 passed, 7 pre-existing Jetstream skips, 0 failures.

**Not done yet:** Phase 6 (Daily/Monthly/Yearly reports with Excel/CSV export via `pxlrbt/filament-excel` — not yet installed).

---

## 2026-07-16 — Session 1 (continued): Phase 4 (request → approval → issuance UI) COMPLETE

**Built:** `StockRequestResource` — Create page (notes + a repeater of product/qty rows) and a View page (Filament Infolist for the request header + `ItemsRelationManager` for the itemized workflow). No Edit page at all: once submitted, a request's items only change through the guarded Approve/Reject/Issue actions, never a raw form — deliberately, matches the "rejected items are terminal" one-directional status philosophy already established in Phase 2.

**Key wiring decision:** the Create form's item repeater is plain array data, *not* a relationship-bound field. `CreateStockRequest::handleRecordCreation()` is overridden to create the `StockRequest` then loop `$stockRequest->addItem($product, $qty)` for each row inside a `DB::transaction()` — this is what actually enforces the item-group ordering permission (§3a) server-side. If `addItem()` throws `InventoryRuleException`, the transaction rolls back (no orphaned half-created request) and the exception is caught to show a Filament notification + `$this->halt()`, rather than a raw 500. Tested explicitly: a denied product results in zero rows in both `stock_requests` and `stock_request_items`.

**`ItemsRelationManager`** has no create/edit/delete actions (items are addition-only, added above) — instead: Approve, Reject, Issue (each a `Tables\Actions\Action` with its own form, gated by `can('approve_request')` / `can('issue_request')`, calling the Phase 2 model methods and catching `InventoryRuleException` into a notification), plus a read-only "View Trail" action using an Infolist with `RepeatableEntry` for the approval and issuance history. Diverges from the original design doc, which sketched one combined approve/reject form — two separate row actions turned out simpler and is what actually got built.

**Notifications:** requester gets a Filament database notification on approve/reject/issue (`sendToDatabase($record->stockRequest->requester)`). Needed `php artisan make:notifications-table` first — nothing in this project had created the standard Laravel `notifications` table yet.

**Query scoping:** same "lacks an elevated role" pattern as `ProductResource` — a pure Demander only sees their own requests (`where('requester_id', ...)`), Admin/Approver/Storekeeper see everything they have permission to view at all (Supplier gets no `view_any_stock::request` permission at all, so this resource is simply unreachable for that role — no special-case code needed for that exclusion).

**Permissions:** extended `PermissionSeeder` in place rather than creating a new seeder — `view_any_stock::request` / `view_stock::request` to Approver, Storekeeper, Demander; `create_stock::request` to Demander only. Same Shield "::" naming quirk as `UserGroup`/`ItemGroup` (compound model name → literal `::` in the permission string) — confirmed this affects `StockRequest` too, not just "Group"-suffixed models; still cosmetic/harmless.

**Tests:** `tests/Feature/StockRequestWorkflowUiTest.php` (7 tests) — end-to-end through the actual Livewire/Filament layer via `Livewire::test()`/`Livewire::actingAs()`, not just the underlying model methods (those were already covered in Phase 2's `InventoryWorkflowTest`). Covers: create with a permitted product, create rejected+rolled-back for a denied product, list scoping, approve within limit, approve-over-limit blocked by the form's own `maxValue` validation, issue capped by stock, and the cancel action. Plus 2 more paths added to `AdminPanelSmokeTest` for the new resource. Full suite: 61 tests, 54 passed, 7 pre-existing Jetstream skips, 0 failures — all ran clean on the first real attempt this time (no repeat of Phase 3's canAccessPanel/define_via_gate surprises, since both fixes already landed).

**Not done yet:** Phase 5 (low-stock alerts widget/page + the Settings page for editing the threshold — `Setting::get('low_stock_threshold')` already exists and is used by `Product::isLowStock()`, but there's no UI yet to change it).

---

## 2026-07-14 — Session 1 (continued): Phase 3 (Filament CRUD resources) COMPLETE

**Built:** 6 Filament resources — `CategoryResource`, `UnitResource`, `ItemGroupResource`, `UserGroupResource`, `ProductResource`, `UserResource`. All generated via `make:filament-resource --generate` then substantially hand-rewritten (the auto-inferred forms were unusable as-is — e.g. User's included raw `two_factor_secret`/`current_team_id` fields, Product's let `current_stock` be freely edited). Notable specifics:
- `ProductResource`: `current_stock` is disabled/non-dehydrated on edit (only ever changes via `recordStockIn()` or issuance, never direct form edit); a "Stock In" row action gated on the `record_stock_in` permission; `getEloquentQuery()` scopes the list to permitted products for anyone without Admin/Approver/Storekeeper/Supplier role (i.e. pure Demanders) — deliberately keyed off "lacks an elevated role" rather than "has Demander role," so a hypothetical dual-role user isn't wrongly restricted.
- `UserGroupResource`: the "Permitted Item Groups" multi-select uses `saveRelationshipsUsing()` to stamp `granted_by` on the pivot — Filament's relationship-select doesn't auto-populate extra pivot columns.
- `UserResource`: rebuilt from scratch rather than trimmed — roles + user-groups multi-selects, password only required/hashed on create.

**Two real bugs found and fixed, not just test artifacts** — both documented in `CLAUDE.md` "Conventions" now so they don't regress:
1. **`User` didn't implement `Filament\Models\Contracts\FilamentUser`.** Filament's own `Authenticate` middleware denies panel access to *everyone* whenever `config('app.env') !== 'local'` unless the user model implements `canAccessPanel()`. This looked completely fine in manual dev testing (APP_ENV=local) and would have silently locked out every single user the moment this deployed to staging/production. Fixed by adding the interface + a `canAccessPanel()` that requires at least one role.
2. **Shield's `super_admin.define_via_gate: false` (the package default, which this project's config had) does not give Admin a real bypass.** It instead makes `shield:generate` physically attach every currently-generated permission to the Admin role's DB row. That's fragile in two ways: it silently breaks on any fresh database that hasn't had `shield:generate` re-run (which is exactly what the `ims_v1_testing` DB is — migrations run fine, but nothing had ever run `shield:generate` against it, so Admin had zero permissions there and every panel page 403'd). Found by chasing a 403 through Filament's middleware stack via `withoutExceptionHandling()` in a throwaway test — the trail: `Authenticate` middleware → fine (canAccessPanel now works) → `CanAuthorizeResourceAccess` → `abort_unless($resource::canViewAny())` → false, because `$user->can('view_any_product')` was false despite `hasRole('Admin')` being true. Traced into `vendor/bezhansalleh/filament-shield/src/FilamentShield.php`'s `giveSuperAdminPermission()` to find the real mechanism. Fixed by flipping `define_via_gate` to `true`, which registers a real `Gate::before()` keyed on `hasRole('Admin')` — this is what "Admin bypasses everything" should actually mean, and now needs no re-seeding step to keep working on a fresh DB.

**Cosmetic Shield quirk worth knowing about, not worth fixing:** permissions for `UserGroup` and `ItemGroup` (compound-word model names) get generated as e.g. `view_user::group` / `view_item::group` — a literal `::` in the permission name — instead of the `view_user_group` you'd expect. Confirmed harmless (permission strings still work fine end-to-end, `PermissionSeeder` just has to reference the real generated names, not guessed ones), just looks odd in the Shield Roles UI. Not investigated further since it doesn't affect correctness.

**Also built:** `PermissionSeeder` — 4 custom action permissions (`approve_request`, `issue_request`, `record_stock_in`, `view_reports`, none of which map to a Filament resource CRUD action so `shield:generate` doesn't create them) plus role→permission assignment per `PLAN.md` §4's matrix. Wired into `DatabaseSeeder` after `RoleSeeder`. **Order now matters for a fresh setup:** `migrate:fresh` → `shield:generate --panel=admin --all` → `db:seed` — `shield:generate` isn't a migration, it writes straight to the DB, and `PermissionSeeder` needs those rows to already exist.

**Tests:** `tests/Feature/AdminPanelSmokeTest.php` (5 tests) — every new resource's index/create/edit pages load for Admin, the Demander product-scoping actually filters what's rendered (not just what's queryable), and a roleless user is correctly forbidden. Full suite: 54 tests, 47 passed, 7 pre-existing Jetstream skips, 0 failures.

**Not done yet:** Phase 4 (request → approval → issuance Filament UI) — `StockRequestResource` doesn't exist yet, so the domain logic built in Phase 2 (`StockRequest::addItem()`, `StockRequestItem::approve/reject/issue()`) has no UI in front of it yet. That's next.

---

## 2026-07-14 — Session 1 (continued): User Groups / Item Groups added (extends Phase 2)

**New requirement from the user, mid-session, after Phase 2 was already complete:** Demanders should be organizable into user-groups by Admin, and those groups can be granted/denied permission to order from specific item-groups — explicitly **not** the same thing as product categories (categories stay the browsing/search taxonomy; item-groups exist only to gate ordering). Full design is in `PLAN.md` §3a — read that before touching this feature again, don't re-derive it from the code.

**Cardinality, confirmed with the user:** both `User↔UserGroup` and `Product↔ItemGroup` are many-to-many (not the simpler one-to-many I'd have defaulted to). A user's permitted item-groups = union across all their groups. `UserGroup↔ItemGroup` (the actual permission grant) is a many-to-many allow-list — row exists = permitted, no separate boolean.

**Resolved defaults I chose (not asked, but flagged as reversible in PLAN.md §3a):** a product with zero item-groups is unrestricted (open to all demanders) rather than locked to none — otherwise every unclassified product would be unorderable by default, which seemed like a bad rollout experience. Admin bypasses this layer entirely, same as it bypasses Shield permissions.

**What's built:** migrations for `item_groups`, `user_groups`, and 3 pivot tables (`item_group_product`, `user_user_group`, `item_group_user_group` — the last one carries `granted_by` + timestamps for auditability). Models `ItemGroup`, `UserGroup`, plus `Product::itemGroups()`, `User::userGroups()`, `User::permittedItemGroupIds()`, `User::canOrderProduct()`. `StockRequest::addItem()` is now the sanctioned way to add items to a request — it's the enforcement point, following the same guarded-method pattern as `approve()`/`issue()`/`recordStockIn()`. 7 new feature tests in `tests/Feature/ItemGroupOrderingPermissionTest.php`, full suite (49 tests, 42 passed + 7 pre-existing Jetstream skips) green.

**Same migration-ordering gotcha as Phase 2, again:** `create_item_group_product_table` got a timestamp that alphabetically sorted before `create_item_groups_table` despite depending on it — renamed it to a later timestamp before running. Worth just expecting this every time and checking `ls database/migrations | sort` after any batch of `make:migration` calls, rather than being surprised by it again.

**Not done yet:** the Filament-layer enforcement (ProductResource scoping for Demanders, the request-form product picker filtering, UserGroupResource/ItemGroupResource UI) — that's Phase 3, in progress next in this same session.

---

## 2026-07-14 — Session 1 (continued): Phase 2 (schema & models) COMPLETE

**Phase 2 is done** per `.claude/design/02-schema-and-models.md`. All 9 domain tables migrated (categories, units, products, settings, stock_requests, stock_request_items, request_approvals, stock_movements, stock_issuances), all 9 Eloquent models + factories written, 4 backed enums in `app/Enums/`. 10 feature tests across two files, all passing.

**Environment change worth knowing:** this machine has no `pdo_sqlite`, so the default Laravel testing setup (`DB_CONNECTION=sqlite`, `:memory:`) doesn't work here. `phpunit.xml` now points tests at a separate MySQL database `ims_v1_testing` (created alongside `ims_v1`). `config/database.php` also has an extra `mysql_lock_test` connection — a second independent connection to the same database, used only by `tests/Feature/ConcurrentIssuanceLockTest.php` to prove `lockForUpdate()` is a real DB-level lock (that test deliberately skips `RefreshDatabase` and cleans up its own rows, since RefreshDatabase's transaction-wrapping would hide the setup data from the second connection).

**Gotcha hit and fixed:** `php artisan make:enum Enums/Foo --string` inconsistently nested 3 of the 4 generated enums under `app/Enums/Enums/` instead of `app/Enums/` — had to `rm -rf app/Enums/Enums` and rewrite them at the correct path. Also `php artisan make:migration` gave `request_approvals` and `stock_issuances` timestamps that would have run them *before* tables they have FKs into (`stock_request_items`, `stock_movements`) — renamed the migration files to fix ordering before running them. Worth double-checking migration timestamp order whenever generating several FK-dependent migrations in a batch like this.

**Design decisions made during implementation** (not pre-specified in PLAN.md, recorded here so they're not re-litigated later):
- `Product::recordStockIn()` / `recordStockOut()` — two named methods instead of one generic `applyStockMovement(type, qty)` as originally sketched in the design doc. Reads clearer at call sites; `adjustment` stays in the `StockMovementType` enum for later but has no method yet (not building it until Phase 3+ actually needs manual stock corrections).
- All three business-rule violations (approval > requested, issuance > approved, issuance > stock) throw a single `App\Exceptions\InventoryRuleException` rather than three distinct exception classes — the message is written to be shown directly to the user, one exception type was enough for the Filament actions to catch uniformly.
- `StockRequestItem::approve()` blocks reducing `approved_qty` below what's already been `issued_qty` (not explicitly required, but the alternative — silently letting an item's approved amount drop under its already-issued amount — allows a nonsensical negative "remaining approved". Simple to enforce.
- `StockRequestItem::reject()` blocks rejecting an item that already had stock issued (same rationale).

**Not done yet / next up:** Phase 3 (Filament CRUD resources) per `.claude/design/03-filament-resources.md` — CategoryResource, UnitResource, ProductResource (with the stock-in action), UserResource role tab, then `shield:generate` to wire up permissions per the matrix in PLAN.md §4.

---

## 2026-07-14 — Session 1 (continued): Phase 1 static prototype COMPLETE

**Phase 1 is done.** All 14 pages listed in `.claude/design/01-static-prototype.md` exist under `static_prototype/pages/`: `login.html`, `dashboard-admin.html`, `dashboard-approver.html`, `dashboard-storekeeper.html`, `dashboard-demander.html`, `dashboard-supplier.html`, `products.html`, `product-form.html`, `request-new.html`, `request-detail.html`, `approval-queue.html`, `issuance-screen.html`, `stock-alerts.html`, `reports.html`. Shared design system in `assets/css/style.css` + `assets/js/app.js` (real vanilla-JS tab switching on reports.html, real modal open/close on approval-queue.html and issuance-screen.html — not just visual mockups, actually clickable).

**Verified:** every page has balanced `<div>`/`</div>` tags (scripted check), and all 14 served HTTP 200 from a local `python3 -m http.server`. Not visually screenshotted (no browser/screenshot tool available in this environment) — if picking this up next, worth the user actually opening `static_prototype/pages/dashboard-admin.html` in a browser to eyeball it before Phase 2 starts.

**Key design decisions baked into the pages** (carry these into the real Filament build in Phase 3+):
- Badge colors: pending=amber, approved=blue, partial=purple, issued=green, rejected=red, cancelled=gray.
- Each role's dashboard shows only the nav items that role should see (Shield-gated later) — Demander: Dashboard/Products/My Requests/New Request only; Supplier: Dashboard/Inventory/Alerts only (fully read-only, no action buttons anywhere on `dashboard-supplier.html`); Approver adds Approvals; Storekeeper adds Issuance; Admin sees everything including Users & Roles / Settings / Audit Log (those three are placeholder `#` links — no dedicated static page was built for them since they're standard CRUD/settings screens, low ambiguity).
- `request-detail.html` is the reference for the "fully trackable" requirement — per-product timeline showing requested → approved (with approver + remarks) → issued (possibly partial, with storekeeper + remarks + explicit note about why partial e.g. insufficient stock).
- `approval-queue.html` / `issuance-screen.html` modals show the qty cap rule directly in the hint text (approved ≤ requested; issued ≤ min(remaining approved, current stock)) — the real Filament actions must enforce this server-side, the static prototype only shows it as a UI hint.

**Also resolved this session:** the 4 open questions from PLAN.md §8 were answered with defaults rather than blocking on the user (documented as "Resolved defaults" in PLAN.md §8, all reversible/low-stakes): no unit conversion in v1, multi-item requests allowed, low-stock threshold defaults to 10, rejected items are terminal (new request needed, no resubmission).

**Not done yet / next up:** Phase 2 (schema & models) per `.claude/design/02-schema-and-models.md` — migrations for categories, units, products, settings, stock_requests, stock_request_items, request_approvals, stock_movements, stock_issuances, plus Eloquent models and the PHP backed enums. PLAN.md itself has not had an explicit "yes this looks right" from the user (they said "continue" which has been treated as approval to keep moving), so if anything in Phase 1's pages looks off to them, expect possible revisions before Phase 2 locks in the schema.

---

## 2026-07-14 — Session 1: environment + base install

**Done:**
- Clarified requirements with the user (approval ceiling, threshold scope, stock inflow ownership, roles, org model, UI structure, report format, Jetstream stack) — all recorded in `PLAN.md` §1.
- Confirmed local dev environment: PHP 8.5.4, Composer 2.10.2, Node 24, MySQL 10.4 via LAMPP at `/opt/lampp` (not a systemd service — started manually, and `lampp status` can misreport MySQL as down due to a stale PID file even when it's actually reachable on port 3306).
- Created MySQL database `ims_v1`.
- Installed Laravel 13.19 fresh into this directory.
- Installed and migrated Jetstream (Livewire stack, Teams disabled).
- Had to get the user to manually install PHP extensions via `sudo apt` (no TTY available for sudo in this tool): `intl`, `zip`, `gd`, `bcmath`, `mbstring`. All confirmed active.
- Installed Filament 3 admin panel at `/admin`.
- Installed Filament Shield + `spatie/laravel-permission`, migrated permission tables.
- Added `HasRoles` trait to `User` model, registered `FilamentShieldPlugin` in `AdminPanelProvider`.
- Set `config/filament-shield.php` `super_admin.name` to `Admin` (so our `Admin` role IS Shield's super-admin, rather than having two separate concepts).
- Created `RoleSeeder` (Admin, Approver, Storekeeper, Demander, Supplier), wired into `DatabaseSeeder`, which also creates `admin@example.com` and assigns the `Admin` role. Seeded successfully against MySQL.
- Verified `php artisan about` boots clean and confirms the MySQL connection.
- Wrote `PLAN.md`, `CLAUDE.md`, this file, `.claude/design/` phase docs, and scaffolded `static_prototype/`.

**Not done yet / blocked on:**
- No domain migrations/models exist yet (Category, Unit, Product, StockRequest, etc.) — deliberately deferred. Per the user's own process instructions, static page design should be agreed on first, and PLAN.md itself needs their sign-off before Phase 2 (schema) starts.
- `static_prototype/` currently only has folder structure + a README, not actual mockup pages — those are Phase 1, gated on PLAN.md approval.
- Git repo has not been initialized yet — ask before doing so if it wasn't part of the original scaffolding request, since `git init` + first commit is a reasonable default but worth flagging.
- Open questions in PLAN.md §8 (unit conversion, multi-item requests, default threshold value, resubmission of rejected items) are not yet answered.

**Environment gotchas worth remembering:**
- This machine runs low on free RAM under normal usage (heavy Chrome/VSCode load) — a `composer require` got OOM-killed (exit 137) once mid-session. Fix was `composer install` (reads existing lock file, lighter) rather than re-running `require`. Worth trying that first if a future install seems to hang or die silently.
- `sudo` cannot be run from the Bash tool here (no TTY) — always ask the user to run privileged commands manually and confirm back.
