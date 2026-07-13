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

## Local dev environment notes

- MySQL is **not** a systemd service here — it's started via LAMPP (`/opt/lampp/lampp start`). `/opt/lampp/lampp status` may falsely report "MySQL is not running" due to a stale/permission-denied PID file even when it's actually up — verify with `/opt/lampp/bin/mysql -u root -e "SELECT 1"` or `ss -tlnp | grep 3306` instead of trusting that status line.
- `sudo` commands cannot be run from this tool (no TTY for password entry) — if a new PHP extension or system package is ever needed, ask the user to run it manually rather than attempting `sudo` directly.
- Composer/npm installs on this machine can hit low free RAM (heavy swap usage from browser/IDE). If a `composer require` gets killed (exit 137), retry with plain `composer install` first (reads the already-updated `composer.lock`) before re-running `composer require` — it's lighter on memory.

## Conventions for this project

- `Admin` role = Filament Shield's `super_admin` (configured in `config/filament-shield.php`) — bypasses all permission checks. Don't create a separate `super_admin` role.
- Business rules (approval ≤ requested, issuance ≤ approved ≤ stock) must be enforced in backend actions/transactions, not just Filament form validation — see PLAN.md §3.
- Stock changes always go through `StockMovement` (append-only ledger). Never mutate `products.current_stock` directly outside of that flow.
- Low-stock threshold is a single **global** setting (not per-product) — a deliberate, documented trade-off (see PLAN.md §1).
- Suppliers are read-only. They never create stock-in entries.
- No `Organization` model — this codebase is redeployed fresh per organization.
- Full audit trail requirement is met two ways: (1) domain tables are append-only where it matters (`request_approvals`, `stock_issuances`, `stock_movements`), and (2) `spatie/laravel-activitylog` (Phase 7) provides a general-purpose changelog across all models.

## Key commands

```bash
php artisan serve                 # dev server
php artisan migrate               # run migrations
php artisan db:seed               # seed roles + admin user (admin@example.com)
php artisan shield:generate --panel=admin --resource=all   # regenerate Shield permissions after adding a new Filament resource
npm run dev                       # vite dev (Filament/Jetstream assets)
```

## Workflow expectations

Build phase-by-phase per `PLAN.md` §5, and check in before starting the next phase rather than building everything at once. Update `.claude/memory/CONTEXT.md` at the end of a work session with what changed and what's next, and add any new terminal commands run to `PLAN.md` §7.
