# Rider and Waiter are new additive roles, restaurant-scoped, open-pool

The original system has no concept of a delivery courier or a pickup-handoff staff member — order
handoff wasn't modeled at all beyond the status enum. Delivery/pickup handoff needed a real actor
distinct from Manager, so two new roles are introduced, following the existing additive-role-table
pattern (ADR-0005): presence of a row is the role, a user may hold this alongside any other role.

- `Rider`: scoped to one Restaurant (a `restaurant_id` FK on the role row — one level up from
  Chef's Kitchen scoping, since delivery isn't kitchen-specific). Performs the
  `AWAITING_DELIVERY → OUT_FOR_DELIVERY → CLOSED` handoff.
- `Waiter`: scoped to one Restaurant, same shape. Performs the `AWAITING_PICKUP → CLOSED` handoff.

Both are open-pool: any Rider/Waiter scoped to the order's restaurant can act, first to act wins.
No dispatch/assignment mechanism (specific rider assigned to a specific order, load-balancing) —
that is a later phase if the product needs it. See ADR-0010 for how these roles fit the order
status machine.
