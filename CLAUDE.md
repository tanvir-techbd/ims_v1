# CLAUDE.md

Context for Claude Code when working in this repository. See `PLAN.md` for the full architecture/decisions and `.claude/memory/CONTEXT.md` for a running status log — read both before starting work on a new session.

## What this is

Inventory Management System for a single organization (one deployment per org, no multi-tenancy). Workflow: Demander requests items → Approver approves up to the requested quantity → Storekeeper issues up to the approved quantity based on real stock. Fully auditable end to end.

## Stack

- Laravel 13, PHP 8.5
- Filament 3 (single admin panel at `/admin` for every role — Admin, Approver, Storekeeper, Demander, Supplier — visibility controlled by Shield permissions, not separate panels)
- Filament Shield (`bezhansalleh/filament-shield`) + `spatie/laravel-permission` for roles/permissions
- Laravel Jetstream, Livewire stack, Teams disabled — supplies auth/profile/2FA scaffolding
- MySQL via local LAMPP (`/opt/lampp`), database `ims_v1`, host `127.0.0.1:3306`, user `root`, no password
- Local dev PHP extensions required: `intl`, `zip`, `gd`, `bcmath`, `mbstring` (all already installed on this machine)
- No `pdo_sqlite` on this machine — tests run against a separate MySQL database `ims_v1_testing` (see `phpunit.xml`), not the default in-memory sqlite. `config/database.php` also has a `mysql_lock_test` connection (second independent connection, same DB) used only by the row-lock concurrency test.

## Local dev environment notes

- MySQL is **not** a systemd service here — it's started via LAMPP (`/opt/lampp/lampp start`). `/opt/lampp/lampp status` may falsely report "MySQL is not running" due to a stale/permission-denied PID file even when it's actually up — verify with `/opt/lampp/bin/mysql -u root -e "SELECT 1"` or `ss -tlnp | grep 3306` instead of trusting that status line.
- `sudo` commands cannot be run from this tool (no TTY for password entry) — if a new PHP extension or system package is ever needed, ask the user to run it manually rather than attempting `sudo` directly.
- Composer/npm installs on this machine can hit low free RAM (heavy swap usage from browser/IDE). If a `composer require` gets killed (exit 137), retry with plain `composer install` first (reads the already-updated `composer.lock`) before re-running `composer require` — it's lighter on memory.

## Conventions for this project

- `Admin` role = Filament Shield's `super_admin` (configured in `config/filament-shield.php`) — bypasses all permission checks via a real `Gate::before()` (`define_via_gate` is set to `true`, **not** Shield's own default of `false`). Don't create a separate `super_admin` role, and don't revert `define_via_gate` — see the comment in that config file for why the default is actively wrong for this project (it ties Admin's bypass to whichever permissions happened to exist in the DB at the last `shield:generate`, which silently breaks on any fresh database — including the test DB — that hasn't had it re-run).
- `User` **must** implement `Filament\Models\Contracts\FilamentUser` with `canAccessPanel()` — without it, Filament denies panel access to everyone whenever `APP_ENV !== 'local'` (so it looked fine manually in dev and then failed for literally every user in production/testing). Current implementation: any user with at least one role can access the panel.
- Business rules (approval ≤ requested, issuance ≤ approved ≤ stock, item-group ordering permission) must be enforced in backend actions/transactions, not just Filament form validation — see PLAN.md §3 and §3a.
- Stock changes always go through `StockMovement` (append-only ledger). Never mutate `products.current_stock` directly outside of that flow — the `current_stock` field is disabled in `ProductResource`'s edit form for the same reason.
- Low-stock threshold is a single **global** setting (not per-product) — a deliberate, documented trade-off (see PLAN.md §1).
- Suppliers are read-only. They never create stock-in entries.
- No `Organization` model — this codebase is redeployed fresh per organization.
- **Item Groups are not Categories** (PLAN.md §3a) — Category is the browsing/search taxonomy; Item Group exists only to gate which User Groups may order a product. Don't merge these into one concept.
- Full audit trail requirement is met two ways: (1) domain tables are append-only where it matters (`request_approvals`, `stock_issuances`, `stock_movements`), and (2) `spatie/laravel-activitylog` (Phase 7) provides a general-purpose changelog across all models.
- After `php artisan migrate:fresh`, permissions are gone until you re-run `php artisan shield:generate --panel=admin --all` — it writes directly to the DB, not via migration. Run it **before** `db:seed`, since `PermissionSeeder` assigns roles against permissions it expects to already exist. Order: `migrate:fresh` → `shield:generate` → `db:seed`.

## Key commands

```bash
php artisan serve                 # dev server
php artisan migrate:fresh         # rebuild schema
php artisan shield:generate --panel=admin --all   # regenerate Shield permissions/policies (run before db:seed!)
php artisan db:seed               # seed roles, custom permissions, admin user (admin@example.com), settings
npm run dev                       # vite dev (Filament/Jetstream assets)
```

## Workflow expectations

Build phase-by-phase per `PLAN.md` §5, and check in before starting the next phase rather than building everything at once. Update `.claude/memory/CONTEXT.md` at the end of a work session with what changed and what's next, and add any new terminal commands run to `PLAN.md` §7.
