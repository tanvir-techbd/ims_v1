# How to Use This App

A walkthrough of the core workflow — Demander requests → Approver approves → Storekeeper issues → Demander confirms receipt — using the seeded demo accounts. See `CLAUDE.md`/`PLAN.md` for architecture; this file is just "how do I click through a full example."

## 1. Seed the demo data (once)

```bash
php artisan migrate:fresh
php artisan shield:generate --panel=admin --all   # regenerate permissions — must run before seeding
php artisan db:seed                               # roles, permissions, the Admin account, settings
php artisan db:seed --class=DemoDataSeeder         # demo users, products, and one ready-made pending request
```

Then visit `http://localhost:8000` (or wherever `php artisan serve` is running) and click **Sign In**, or go straight to `/admin/login`.

## 2. Demo accounts

Every demo account's password is **`password`**.

| Name | Email | Role | Notes |
|---|---|---|---|
| Admin User | `admin@example.com` | Admin | Sees and can do everything |
| Jane Whitfield | `jane.whitfield@example.com` | Approver | Approves/rejects requested items |
| Miguel Torres | `miguel.torres@example.com` | Storekeeper | Issues approved items from stock |
| Sarah Kim | `sarah.kim@example.com` | Demander | Facilities Team — can order "Facilities Orderable" items + anything unrestricted |
| David Lee | `david.lee@example.com` | Demander | Facilities Team, same permissions as Sarah |
| Fatima Noor | `fatima.noor@example.com` | Demander | IT Department — can order "IT Orderable" items + anything unrestricted |
| Global Supplies Ltd. | `contact@globalsupplies.example.com` | Supplier | Read-only inventory view, no edit/order/issue actions |

A product with no item-group at all (e.g. Hand Sanitizer, Stapler) is orderable by every Demander regardless of group. A product tagged with an item-group (e.g. Safety Gloves → "Facilities Orderable") can only be ordered by a Demander whose user-group has been granted that item-group.

## 3. The core workflow: Order → Approve → Issue → Receive

The seeder already leaves one request sitting in **Pending**, ready to approve — Sarah Kim asked for Whiteboard Markers (15) and Hand Sanitizer (12). Fastest way to see the whole flow end to end is to pick that request up at step 2 below, or start completely fresh with step 1.

### Step 1 — Order (as a Demander)

1. Sign in as `sarah.kim@example.com` / `password`.
2. In the sidebar, go to **Stock Requests → New stock request** (or click **+ New Request** on the dashboard).
3. Products are listed catalog-style, grouped by category (only categories/products your group is actually permitted to order show up at all). Tick the checkbox on each product you need — a quantity field appears next to it as soon as you do. Tick as many as you like, fill in each quantity, optionally add a note, then click **Create**.
4. Your dashboard now shows the request under **My Requests** with status **Pending**.
5. Sign out (avatar menu, top right → Sign out).

### Step 2 — Approve (as the Approver)

1. Sign in as `jane.whitfield@example.com` / `password`.
2. Go to **Stock Requests**, click the pending request (e.g. Sarah Kim's) to open it, and scroll to the **Requested Items** table.
3. For each item, click **Approve** and set a quantity — up to, but never more than, what was requested — with optional remarks. Or click **Reject** to turn one down outright (David Lee's older request already has one rejected item, seeded as an example of what that looks like).
4. The item's status updates to **Approved** or **Rejected** immediately; the parent request's own status follows once every item has a decision.
5. Sign out.

### Step 3 — Issue (as the Storekeeper)

1. Sign in as `miguel.torres@example.com` / `password`.
2. Go to **Stock Requests**, open the now-approved request, and find the same **Requested Items** table.
3. For each approved item, click **Issue** and set a quantity — capped by both what was approved and what's actually in stock right now. If stock is short you'll only be able to issue up to what's available (try this on Safety Gloves, seeded with just 3 in stock).
4. Stock is deducted immediately and recorded in the append-only stock ledger; the item's status becomes **Issued** or **Partially Issued**.
5. Sign out.

### Step 4 — Confirm receipt (as the Demander)

1. Sign in as `sarah.kim@example.com` / `password` again (the original requester).
2. Go to **Stock Requests** — the request now shows status **Issued** or **Partially Issued**.
3. Click **Mark as Received** on that row and confirm. This is the requester's own acknowledgement that they physically got the item(s) — only they (or Admin) can do it, and only once something has actually been issued. Status becomes **Received**, the final state in the trail.

### Step 5 — See the full trail

Sign in as any role that can view the request (Admin, Approver, or Storekeeper, or the original Demander) and open it from **Stock Requests**. On the **Requested Items** table, click **View Trail** on any item to see its complete history: who approved how much and why, who issued how much and when.

## 4. Other things worth trying

- **Stock Alerts** — products at or below the global low-stock threshold (Settings → Global Low-Stock Threshold, default 10). Several demo products are seeded deliberately low to populate this.
- **Reports** (Admin/Approver/Storekeeper) — Products Issued Report and User Activity Report, both filterable by day/month/year and exportable to CSV.
- **Audit Log** (Admin) — a full changelog of every create/update across the system, powered by the seeded demo data too.
- **User Groups / Item Groups** (Admin) — where the Demander ordering restrictions above are actually configured; try creating a new Item Group, assigning a product to it, and watching a Demander's product list change.
- **Dark mode** — sun/moon/system icons in the top bar, next to the search box.
- **Supplier view** — sign in as `contact@globalsupplies.example.com` to see the read-only inventory view suppliers get (no edit, order, or issue actions anywhere).

## 5. Resetting back to a clean demo

Re-run the three seed commands from step 1 — `migrate:fresh` wipes everything, so you'll get the same starting scenario every time.
