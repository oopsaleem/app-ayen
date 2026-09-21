# No server-side Cart model; checkout submits one atomic order-creation request

Considered a persisted `Cart` entity (server-side line items, cart-abandonment tracking,
multi-device sync) and rejected it for this phase — no product requirement calls for it, and the
original system never had one either (checkout was pure client-side state, submitted as a single
`createOrder` call). Chosen: cart stays frontend-only React state; `POST /orders` creates the
`Order` and all its `OrderDish` rows together, atomically, in one request. Revisit only if a
concrete need emerges (e.g. persistent cross-device carts, saved-for-later).
