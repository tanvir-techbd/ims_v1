# Project Context Log

Running log of decisions and status across sessions. Newest entry on top. See `PLAN.md` for the stable architecture reference — this file is for things that change session to session.

**If resuming after an interruption:** check the top entry below for "Not done yet" / "In progress" items, then check `git log --oneline` in the project root — every meaningful chunk of work is committed as it's finished, so the commit history plus this file together tell you exactly where things stand. Also check the TODO list state if the harness surfaces one.

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
