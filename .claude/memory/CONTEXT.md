# Project Context Log

Running log of decisions and status across sessions. Newest entry on top. See `PLAN.md` for the stable architecture reference — this file is for things that change session to session.

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
