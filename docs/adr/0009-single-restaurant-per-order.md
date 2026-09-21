# An order may only contain dishes from one restaurant

The original system had no guard preventing a single order from mixing dishes sourced from
different restaurants/companies — flagged as a gap in `idea/02-features.md`, not a pattern to
reproduce. A multi-restaurant order doesn't make operational sense here: pickup/delivery,
kitchen-acceptance, and fee calculation all assume one restaurant per order.

Chosen: at order creation, the server derives the order's restaurant from the first `OrderDish`'s
`Dish → Kitchen → restaurant_id` chain and rejects the request (422) if any other line resolves to
a different restaurant. `Order.restaurant_id` is stored denormalized (not re-derived via joins on
every read) — cheap for manager/rider/waiter order-list queries, and avoids the class of bug the
original had in `getOrderTimeline` (looked up a restaurant-specific threshold using the wrong id
entirely because nothing denormalized the restaurant id onto the order).
