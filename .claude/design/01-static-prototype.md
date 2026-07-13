# Phase 1 — Static Prototype

Status: **done** (2026-07-14). All 14 pages built under `static_prototype/pages/`, shared design system in `static_prototype/assets/`. See `.claude/memory/CONTEXT.md` for what carried over into the design conventions for later phases.

## Goal

Plain HTML/CSS (no backend, no Livewire) pages in `static_prototype/` so the user can agree on layout/UX before any Filament resource is built. Keep it framework-agnostic — this is throwaway/reference, not code that gets wired up later. A shared stylesheet + a bit of vanilla JS for interactive bits (tabs, modals) is fine; no build step required so the user can just open files in a browser.

## Pages to mock up (one HTML file per page, listed in `static_prototype/pages/`)

- `login.html` — matches Jetstream's default login but themed for this app
- `dashboard-admin.html` — KPI tiles (total products, low-stock count, pending requests, today's issuances) + recent activity
- `dashboard-approver.html` — pending approval queue front and center
- `dashboard-storekeeper.html` — pending issuance queue + quick stock-in action
- `dashboard-demander.html` — "my requests" list + new request button
- `dashboard-supplier.html` — read-only inventory table, no actions
- `products.html` — category-filterable, searchable product catalog with stock levels
- `product-form.html` — create/edit product (category, unit, sku, description)
- `request-new.html` — multi-item request form (product picker + qty per line)
- `request-detail.html` — single request showing items, per-item status, and the full approval/issuance trail
- `approval-queue.html` — table of pending items awaiting this approver, inline approve/reject with qty input
- `issuance-screen.html` — table of approved items awaiting issuance, inline issue with qty input capped by stock
- `stock-alerts.html` — low-stock product list
- `reports.html` — daily/monthly/yearly tabs, table + export button

## Notes

- Use realistic placeholder data, not lorem ipsum, so the workflow reads clearly (e.g. real product names like "A4 Paper Ream", "Safety Gloves (Box of 12)").
- Every status shown (pending/approved/rejected/issued/partial) should use a consistent color/badge convention across all pages — decide it here once, reuse everywhere in the real Filament build.
- Don't build responsive/mobile polish at this stage — desktop layout is enough to validate the workflow.
