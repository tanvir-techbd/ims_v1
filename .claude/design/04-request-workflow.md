# Phase 4 — Request → Approval → Issuance Workflow

Status: **done** (2026-07-16). See `.claude/memory/CONTEXT.md` for what actually shipped and where it diverges from this doc's original sketch (mainly: Approve/Reject/Issue ended up as three separate row actions on the items relation manager rather than one combined form, and there's no StockRequest edit page at all — only create + view, since edits-after-submission should go through the guarded workflow actions, not a raw form).

## `StockRequestResource`

- Demander-facing create form: multi-item (repeater: product select + requested_qty), notes field. The product select must only offer products the demander is permitted to order (§3a in PLAN.md) — filter the select's options query, don't just rely on the backend guard to reject after the fact (though the backend guard is still required, see below).
- Items are added via `StockRequest::addItem(Product $product, int $requestedQty)` (added in the Phase 2 extension for §3a) — never create `StockRequestItem` rows directly from the Filament form; this is what actually enforces the ordering-permission rule server-side, catch its `InventoryRuleException` and surface the message.
- Table: requester, item count, overall status badge, created_at. Demanders see only their own (scope by `requester_id` when not Admin/Approver/Storekeeper).
- Detail/relation manager showing each `StockRequestItem` with its full trail (`request_approvals` + `stock_issuances`) inline — this is the "fully trackable" requirement, make it visually obvious who did what and when.

## Approve action (on `StockRequestItem`, gated by `approve_request` permission)

- Form: approved_qty (max = requested_qty, validated server-side not just in the form), remarks, decision (approve/reject).
- On submit: insert `RequestApproval` row, update `StockRequestItem.approved_qty` + `status`, call `StockRequest::recomputeStatus()`.
- Notify the requester (Filament database notification) of the decision.

## Issue action (on `StockRequestItem`, gated by `issue_request` permission)

- Only enabled once item status is `approved` or `partially_issued`.
- Form: issue_qty (max = approved_qty - already-issued, AND ≤ product.current_stock — show both caps in the UI).
- On submit, inside a DB transaction with `Product::lockForUpdate()`:
  1. Re-check current_stock under the lock.
  2. Insert `StockIssuance`.
  3. Insert linked `StockMovement` (type=out, reference to the StockIssuance).
  4. Decrement `product.current_stock`.
  5. Update `StockRequestItem.issued_qty` + `status`.
  6. Call `StockRequest::recomputeStatus()`.
- If stock is insufficient at issuance time, issue only what's available (per requirements: "storekeeper can issue the exact amount based on actual availability") and leave the item `partially_issued` for a later top-up — don't hard-fail the whole action.

## Acceptance criteria

- A request with 3 items can end up with each item independently pending/approved/rejected/issued, and the parent request status reflects the aggregate correctly.
- Attempting to approve more than requested, or issue more than approved/in-stock, is rejected with a clear Filament notification, not a silent clamp.
