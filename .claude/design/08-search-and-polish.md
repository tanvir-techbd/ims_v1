# Phase 8 — Search & Polish

Status: **done** (2026-07-16) — final phase of the original `PLAN.md` §5 plan. See `.claude/memory/CONTEXT.md` for what shipped, including the automated (not manual) permissions-matrix re-verification and a real bug the demo seeder caught in itself before it ever shipped.

## Global search

Configure Filament global search on `ProductResource` (name, sku), `StockRequestResource` (id, requester name), `UserResource` (name, email) — per requirement "search functionality must be available wherever necessary."

## Table-level search/filters

Every resource table should have: text search on the obvious identifying field(s), a category filter on Products, a status filter on StockRequests/Items, a date-range filter where relevant (requests, issuances, audit log).

## Final pass

- Seed realistic demo data (multiple categories/units/products, a handful of requests in various states) for a convincing demo/walkthrough.
- Re-verify the permissions matrix in `PLAN.md` §4 role by role — log into a test user of each role and confirm they see exactly what they should, nothing more.
- Basic UI polish: consistent status badge colors (carry over the convention decided in the static prototype phase), empty states, sensible default sort order per table.
