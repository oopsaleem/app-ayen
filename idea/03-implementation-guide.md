# Implementation Guide (stack-agnostic)

A guide for rebuilding this system's concepts in any backend/frontend stack. Framed as: what to
build, what rules to enforce, and which parts of the current implementation are gaps to **fix**
rather than copy.

## Suggested module/service boundaries

Regardless of framework, these are natural service boundaries based on how the current logic is
actually grouped (not just the entity list):

1. **Identity & Auth** — User + Credentials + AuthProvider + role tables (Admin/Manager/Chef/
   Customer as *presence rows*, not a single enum column) + JWT issuance/verification + a
   role-aggregation step that unions roles from all role tables for the authenticated user on
   every request.
2. **Org/Catalog** — Company, Restaurant, Address, Verification, Kitchen, Chef, ChefKitchen.
3. **Menu** — Category (self-hierarchical tree, global not per-restaurant — or per-restaurant if
   you choose to fix that), Dish, DishOption, ServingSize.
4. **Ordering** — Order, OrderDish, OrderDishOption, DeliveryAddress. This is the module that
   needs the most *new* logic versus the current implementation (see "Rules to enforce" below).
5. **Order Status/SLA** — OrderStatusHistory, OrderStatusThreshold, and (if you complete the
   feature) Alert/Notification. Keep this separate from core Ordering — it's a cross-cutting
   observer of status transitions, not core order logic.
6. **Payments** — Stripe (or equivalent) session creation + **webhook handling** (the current
   system's biggest structural gap — build this as an idempotent, signature-verified webhook
   consumer from day one, not a redirect-only handler).
7. **Reviews** — simple, but add validation the original lacks (see below).

## Authorization model to reproduce

- Roles are **additive rows**, not a single field. A user can be Admin and Manager at once;
  authorization checks must be "does the user have role X among their roles," never "what is
  the user's role."
- Default posture: an endpoint requires *some* authenticated user unless it explicitly lists
  required roles; if it lists roles, access requires at least one match (OR, not AND).
- **Ownership-based access** is layered on top of role-based access, not instead of it: e.g.
  "any authenticated user can call updateOrder, but the specific order must belong to them (as
  customer) or belong to a restaurant owned by their company (as manager)." Reproduce this two-
  layer pattern (coarse role gate + fine-grained ownership check per resource) rather than trying
  to encode ownership into a static role/permission table — the real check here requires a live
  relationship walk (order → dish → kitchen → restaurant → company → manager) that a static ACL
  can't express cleanly.
- Admin is the universal override for row-level ownership checks (an admin can act on any
  customer's resource). Keep this override narrow and explicit — don't let it silently apply to
  every check in the system without an explicit `roles` allowlist per check, as the current
  helper does (default override role list is configurable per call site, not global).

## Business rules to enforce (some are gaps to add, not just document)

Order creation:
- Reject orders with zero line items (existing rule — keep it).
- Look up the customer by their authenticated id, not a client-supplied id that could diverge from
  the authenticated user (the current code does an extra internal lookup by
  `orderData.customerUid` even though row-level permission already pinned it to the authenticated
  user — keep both the auth-layer check and the DB-existence check; they protect against different
  failure classes).
- **New rule to add:** recompute every `OrderDish.unitPrice`/`totalPrice` and the order's
  `total`/`vat`/`delivery`/`paymentFee` server-side from the current Dish/DishOption/ServingSize
  prices at order-creation time, and reject (or at minimum log/flag) any client-submitted total
  that doesn't match. The current implementation trusts client-submitted totals entirely, which is
  a price-tampering vector in a real payment system.
- **New rule to decide:** whether an order may span multiple restaurants. If not (the more
  typical restaurant-ordering product decision), validate at creation time that every OrderDish's
  Dish resolves (via Kitchen) to the same Restaurant, and reject mixed-restaurant carts.
- Snapshot pricing/names onto the order line at creation time (`OrderDish.unitPrice`,
  `servingsNameEn/Ar`, `OrderDishOption.nameEn/Ar/price`) so later menu edits never retroactively
  change historical orders — this snapshotting pattern is correct in the current design; keep it.

Order status transitions:
- Define an explicit legal-transition table instead of allowing any status → any status. Based on
  the field names/timestamps present, the intended graph is:
  `PENDING → CONFIRMED → PREPARING → READY → DELIVERING → CLOSED`, with `CANCELLED` reachable from
  any non-terminal state. Decide and enforce which states permit cancellation (e.g. maybe not once
  `DELIVERING`) — the current code has no such restriction.
- On every transition: stamp the new status's timestamp, write an OrderStatusHistory row
  containing the duration (minutes) spent in the *previous* status, and check the new status
  against the applicable OrderStatusThreshold (restaurant-specific if one exists, else global).
- Fix the threshold lookup bug from the original: resolve the order's **restaurant id** by walking
  `OrderDishes → Dish → Kitchen → restaurantId` (using, e.g., the first dish, or better, validating
  all dishes share one restaurant per the multi-restaurant-order decision above) before querying
  `OrderStatusThreshold` — do not use the order's own id as a stand-in for restaurant id.
- If you complete the SLA-alerting feature (the current system only logs breaches to console): add
  a persisted Alert entity carrying `AlertType` (`APPROACHING`/`BREACHED`) and a Notification
  dispatch keyed by `NotificationType` (`EMAIL`/`SMS`/`PUSH`) — these two enums already exist in
  the original schema specifically anticipating this, so their presence is a strong signal this
  was planned, not accidental.

Payments:
- Treat "payment succeeded" as authoritative only from a **verified webhook event**, not from a
  browser redirect. Keep the redirect for UX (send the user somewhere nice) but do not let it be
  the mechanism that marks an order paid — the original design's biggest correctness gap.
  - `checkout.session.completed` (or provider equivalent) → mark order `CONFIRMED`, stamp
    `paidAt`/`confirmedAt`, store the payment reference.
  - `payment_intent.payment_failed` (or equivalent) → wire this to the existing
    "payment failed" logic (reset to `PENDING`, record the error) — the original has the service
    method (`updatePaymentFailed`) but never calls it from anywhere; make sure the new
    implementation actually wires the failure path to a route/handler.
- Verify webhook signatures. Make webhook handling idempotent (a webhook can be delivered more
  than once for the same event).
- Cash orders need their own explicit confirmation step (e.g. staff marks "cash collected") rather
  than silently behaving identically to a card order with no payment step at all, which is what
  happens today.

Menu/catalog:
- Enforce "at most one default ServingSize per dish" (originally just a convention, not enforced).
- Enforce a valid rating range on Review (e.g. 1–5) — originally unenforced.
- Keep `isAvailable` (dish) and `isActive` (category) as independent soft-hide toggles distinct
  from deletion — this pattern (soft visibility flags rather than deleting menu items) is worth
  preserving since orders reference dishes historically.
- Decide deliberately whether Category is global (shared across all restaurants, as it is today)
  or per-restaurant — the current global design means every restaurant on the platform draws from
  one shared category tree, which may or may not be the intended product behavior; it reads more
  like an oversight than a considered multi-tenant design choice, so re-examine it rather than
  assuming it's correct.

## Internationalization pattern

The original stores bilingual content as **parallel fields** (`nameEn`/`nameAr`,
`descriptionEn`/`descriptionAr`) directly on each entity, rather than a separate translations
table keyed by locale. This is simple and fast to query (no joins for a localized list) but does
not scale past two languages without a schema change. If more than English/Arabic is anticipated,
consider a normalized `Translation(entityType, entityId, locale, field, value)` table instead;
if only these two languages are ever needed, the parallel-field pattern is fine to keep as-is for
simplicity.

## Theming (light/dark image variants)

Restaurants, Dishes, and (single-image) Categories store **separate image sets per theme**
(`lightImages`/`darkImages`, or `lightImage`/`darkImage`) rather than one image set with a
client-side filter/overlay applied for dark mode. Reproduce this if visual parity with
theme-specific artwork matters; otherwise a single image set with CSS-level dark-mode treatment is
simpler and avoids doubling asset upload/storage work — worth flagging to the product owner as a
choice, not just inheriting.

## Things explicitly *not* present that a rebuild should not assume exist

- No reservation/booking system.
- No inventory/stock tracking.
- No refund handling.
- No multi-currency support (Stripe integration hardcodes USD).
- No audit log beyond order-status history (e.g. no audit trail for menu price changes,
  admin actions, etc.).
- No rate limiting / abuse protection visible in the reviewed code.

## Quick checklist for a new stack

1. Model identity as User + additive role tables (or an equivalent additive-roles pattern in your
   new stack, e.g. a `user_roles` join table) — not a single `role` enum column.
2. Model Company → Restaurant → Kitchen → Dish → (DishOption, ServingSize), with Category as a
   separate hierarchical tree joined to Dish.
3. Model Order → OrderDish → OrderDishOption as **snapshotted** line items, separate from the live
   Dish/DishOption/ServingSize records.
4. Build order-status transitions as an explicit, validated state machine with a history log and
   duration tracking — server-computed, not client-trusted.
5. Recompute all order pricing server-side; never trust client-submitted totals.
6. Build payment confirmation on verified, idempotent webhooks — never on a redirect alone.
7. Implement row-level ownership checks as a second layer beneath role checks, using a live
   relationship walk for manager/company/restaurant scoping rather than a static permission table.
8. Decide (don't silently inherit) whether: restaurants must be verified before orderable,
   categories are global or per-restaurant, orders may span multiple restaurants, and which
   status transitions are legal — all four are ambiguous/unenforced in the current implementation.
