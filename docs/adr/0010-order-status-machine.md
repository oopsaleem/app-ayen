# Order status is an enforced machine, driven by kitchen acceptance and per-dish readiness

The original system's status lifecycle
(`PENDING → CONFIRMED → PREPARING → READY → DELIVERING → CLOSED`, `CANCELLED` from any state) was
never enforced server-side — any transition could be set from any state, including nonsensical
ones (`idea/02-features.md`). It also modeled `PICKUP` orders as passing through `DELIVERING`,
which doesn't describe a pickup order at all. Considered reproducing that lifecycle as-is and
rejected it — this rebuild enforces legal transitions explicitly, and mode-neutral naming was a
grilling decision, not an oversight.

Chosen machine, with `READY`/`DELIVERING` removed (replaced by mode-specific states) and
`DELIVERED` never introduced (collapsed into `CLOSED`):

```
PENDING → CONFIRMED            auto: every OrderKitchen row for the order has accepted_at set
CONFIRMED → PREPARING          auto: any OrderDish.status = preparing
PREPARING → AWAITING_DELIVERY  auto: all OrderDish.status = ready, delivery_mode = delivery
PREPARING → AWAITING_PICKUP    auto: all OrderDish.status = ready, delivery_mode = pickup
AWAITING_DELIVERY → OUT_FOR_DELIVERY   manual: any Rider scoped to the order's restaurant
OUT_FOR_DELIVERY → CLOSED              manual: same Rider action, delivery confirmation
AWAITING_PICKUP → CLOSED               manual: any Waiter scoped to the order's restaurant
(PENDING | CONFIRMED | PREPARING) → CANCELLED   see ADR-0011 for who
```

Two tiers of underlying tracking drive the automatic transitions:

- `OrderKitchen` (one row per distinct kitchen touched by the order): any single chef of that
  kitchen sets `accepted_at`/`accepted_by` — first to act wins, no unanimous agreement across a
  kitchen's chefs required. `PENDING → CONFIRMED` fires when the last outstanding row is accepted.
- `OrderDish.status` (`pending`/`preparing`/`ready`), set manually by a chef per dish. A dish
  cannot move to `preparing` until the order itself is `CONFIRMED` — the order's own state gates
  dish-level actions, rather than checking each dish's kitchen-acceptance independently (which
  would be redundant, since kitchen acceptance is already a precondition of `CONFIRMED`).

Rider and Waiter handoff actions are open-pool: whichever Rider/Waiter scoped to that restaurant
acts first claims the transition (no per-order dispatch/assignment — that's a later phase if
needed). No real barcode-scanning integration exists yet; these are plain status-transition
endpoints a future UI can wire to a scanner.

Dish rejection/decline (a chef unable to fulfill a dish) is explicitly out of scope for this
phase — accept-only. If a kitchen can't fulfill, the only lever today is a Manager/Admin
cancelling the whole order (ADR-0011).

Every transition writes an `OrderStatusHistory` row (timestamp + duration in the previous status),
recorded now even though nothing enforces SLA thresholds against it yet — it exists so a future
SLA/alerting phase has historical data from day one instead of only data from when that phase
starts.
