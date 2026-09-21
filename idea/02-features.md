# Features

Status legend: ✅ fully implemented and working · 🟡 partially implemented · ⬜ not implemented
(schema/scaffolding only, or entirely absent).

## ✅ Authentication & session

- Email/password (`Credentials`) and Google OAuth (`AuthProvider`) sign-in, via NextAuth on the
  frontend and a JWT-based guard on the backend.
- Every GraphQL resolver/REST endpoint that needs auth is protected by an `AuthGuard` that:
  1. Extracts a `Bearer <token>` from the request, verifies it as a JWT, and requires a `uid`
     claim.
  2. Confirms a `User` row exists for that uid (rejects otherwise).
  3. Loads the user's roles by checking for the **existence** of an Admin, Manager, and/or Chef
     row for that uid (three parallel lookups) and attaches the resulting role array to the
     request.
  4. If the route declares required roles (via an `AllowAuthenticated(...roles)` decorator) with
     a non-empty list, access is granted only if the user has **at least one** of the required
     roles. An empty/omitted role list means "any authenticated user."
- **Business rule:** roles are not mutually exclusive. A user can be Admin *and* Manager
  simultaneously; both are true concurrently.
- **Gap:** there is no explicit `Customer` role check anywhere — a request from a user with none
  of Admin/Manager/Chef roles is not specially validated as "is actually a customer"; it's simply
  allowed through generic `AllowAuthenticated()` checks and then scoped by row ownership.

## ✅ Row-level authorization ("own your data")

A shared helper (`checkRowLevelPermission`) enforces that a non-privileged user can only act on
resources tied to their own uid, unless they hold an override role (defaults to `admin` only).
Applied to: order creation (customer must create orders under their own uid), order deletion.

Order **update** has custom, more granular logic (not the shared helper): the requester must be
either (a) the order's owning customer, or (b) a Manager whose Company owns a Restaurant whose
Kitchen contains a Dish that appears as an OrderDish on that order — i.e., a manager may only
touch orders that include at least one dish from a restaurant their company owns. This is
computed via a live nested-relation query at request time, not a cached/denormalized permission.

## 🟡 Restaurant / company management

- Companies, Restaurants, Kitchens, Categories, Dishes, DishOptions, ServingSizes, Managers, Chefs,
  Admins, Addresses all have full CRUD (GraphQL + REST, generated/boilerplate) with almost no
  domain validation beyond foreign-key existence checks surfaced as `BadRequestException`.
- **Missing validation** worth calling out for a rebuild:
  - No check that a Manager's company assignment is unique/sensible, no check preventing a
    Restaurant's address from moving to conflict with another restaurant, no uniqueness
    constraint on restaurant/company names.
  - `ServingSize.isDefault` "exactly one default per dish" is not enforced anywhere.
  - `Category.level` is a plain integer field the application is expected to keep in sync with
    `parentId` depth; nothing recomputes it automatically, and nothing prevents a cycle in the
    parent/child chain.
- Restaurant **verification** (admin approval) is modeled (`Verification` entity, admin-only
  create) but the verified flag is **not consulted anywhere** — an unverified restaurant's menu is
  just as orderable as a verified one today. If "restaurants must be verified before customers can
  order from them" is a real product requirement, it needs to be added.

## ✅ Menu browsing (customer-facing)

- Web app has a public menu page (`(restaurant)/menu`) listing categories (with subcategory
  nesting) and dishes, dish detail with serving-size and option selection, and a cart.
- Dish availability (`isAvailable`) and category active flag (`isActive`) exist as soft toggles;
  whether/where the frontend actually filters on them should be verified against the live UI, but
  the fields exist specifically to support hiding items without deleting them.

## ✅ Cart & checkout (customer-facing)

- Multi-step checkout flow (delivery-mode selection → address/payment → review → confirmation),
  implemented as client-side state (`useCheckoutNavigation`, 4 numbered steps), not a server-side
  checkout session/state machine.
- **Pricing is computed entirely on the client.** The cart/checkout UI computes `total`, `vat`,
  `delivery` (fee), `paymentFee`, and each `OrderDish.unitPrice`/`totalPrice`, then submits all of
  it verbatim in the `createOrder` mutation. **The server does not recompute or validate these
  numbers against the actual Dish/DishOption/ServingSize prices stored in the database.** This is
  a trust-the-client design that a rebuild should not copy as-is (see implementation guide).
- Payment methods: `CARD` (via Stripe Checkout) or `CASH` (order is simply created with
  `paymentMethod: CASH`; no separate cash-confirmation workflow exists — a cash order's `status`
  and payment fields behave the same as a card order's until someone manually progresses it).

## 🟡 Payment (Stripe)

- `POST /stripe` creates a Stripe Checkout session for a given order total (order amount +
  delivery fee + card payment fee, summed then converted to cents), with `orderId`, `uid`, and
  `locale` stashed in session/payment-intent metadata.
- `GET /stripe/success?session_id=...` retrieves the session, reads `orderId`/`locale` back out of
  its metadata, marks the order paid (`updatePaid`: sets status to `CONFIRMED`, stamps
  `confirmedAt`/`paidAt`, stores the Stripe session id as `paymentReference`), then redirects the
  browser to a locale-aware order confirmation page.
- **Significant gaps** (do not reproduce silently in a rebuild — flag/fix them):
  - No **webhook** handler for Stripe events (`checkout.session.completed`,
    `payment_intent.payment_failed`, etc.). Payment confirmation relies entirely on the customer's
    browser being redirected back to `/stripe/success` — if the browser never returns (closed tab,
    network failure after payment), the order is never marked paid even though Stripe charged the
    customer.
  - No **signature verification** on any Stripe-originated request — `/stripe/success` trusts
    whatever `session_id` query param it's given and looks it up directly.
  - `updatePaymentFailed` (resets status to `PENDING`, records `paymentError`) exists as a service
    method but **no controller route calls it** — there's no wired path for the failure case at
    all currently, only the happy path.
  - Currency is hardcoded to `usd` in the Stripe session regardless of any locale/currency
    preference.

## ✅ Order lifecycle / status tracking

Order status is a **linear-ish fixed lifecycle**, not a generic state machine:

```
PENDING → CONFIRMED → PREPARING → READY → DELIVERING → CLOSED
                                                  ↘
                                              (any state) → CANCELLED
```

- `PENDING` is the default on creation (`pendingAt` stamped at creation time).
- Any authorized status update stamps the corresponding `*At` timestamp
  (`confirmedAt`/`preparingAt`/`readyAt`/`deliveringAt`/`cancelledAt`/`closedAt`) via a lookup
  table keyed by the new status.
- **Nothing in the current code enforces which transitions are legal** — e.g. nothing stops
  jumping straight from `PENDING` to `CLOSED`, or moving backwards from `DELIVERING` to
  `PREPARING`. The lifecycle above is the *intended* order implied by the field names and the UI,
  not an enforced state machine. A rebuild should decide the legal transition graph explicitly
  and enforce it server-side (recommended, since the current system does not).
- Every status change writes an `OrderStatusHistory` row and computes how many minutes were spent
  in the *previous* status (via the gap between this transition's timestamp and the previous
  history entry's timestamp).
- `DeliveryMode.PICKUP` orders still go through `DELIVERING` in the schema/enum — there is no
  distinct "ready for pickup, awaiting customer" terminal-ish state; pickup vs. delivery does not
  branch the status lifecycle differently in the modeled enum, which may not match real-world
  pickup flows (worth deciding explicitly in a rebuild).

## 🟡 SLA / threshold breach detection

- `OrderStatusThreshold` rows define a max allowed duration (minutes) per `(status, restaurant)`,
  falling back to a global (`restaurantId = null`) threshold when no restaurant-specific one is
  active.
- On every status change, the system computes the duration just spent in the *new* status's
  history and checks it against the threshold for that status/restaurant; if breached, **the only
  effect today is a `console.log`** — no persisted alert, no notification, no UI surfacing. This
  is scaffolding for an alerting feature, not a working one.
- `getOrderTimeline(orderId)` assembles a full breach-annotated timeline for an order, but has a
  known bug: it looks up the restaurant-specific threshold using `order.id` where it should use
  the order's actual **restaurant id** (derived by walking `OrderDishes → Dish → Kitchen →
  restaurantId`) — see the `// TODO must be the restaurantId` comment in the code. As written,
  restaurant-specific thresholds are effectively never matched by this method (it will look up a
  threshold row by a restaurant id that happens to equal the order id, which is almost always
  wrong) and it silently falls through to the global threshold or `null`.
- `AlertType` (`APPROACHING`/`BREACHED`) and `NotificationType` (`EMAIL`/`SMS`/`PUSH`) enums exist
  in the schema specifically anticipating this feature's completion (persisted alerts + multi-
  channel notification), but **no model, table, or service uses them** — there is no Alert or
  Notification entity at all yet.

## ✅ Order visibility by role

- Customers see only their own orders (`where customerUid = self`).
- Managers see all orders that include at least one dish belonging to any restaurant owned by
  their company (computed via `OrderDishes.Dish.Kitchen.restaurantId IN (company's restaurant
  ids)`), across **all** of that company's restaurants at once — there's no separate
  "restaurant-scoped manager" concept; a manager sees company-wide order volume.
- A manager with no restaurants under their company sees an empty list (defensive early return),
  logged as a warning server-side.
- Chefs have no dedicated order-query scoping in the resolver shown — the web app has a
  `chef/kitchen` page, implying chefs are meant to see orders/items relevant to their kitchen, but
  this should be verified directly against the chef-facing frontend code/queries if reproducing
  that view precisely (not covered by the orders resolver's role branching, which only special-
  cases managers vs. everyone-else-as-customer).

## ⬜ Not implemented at all

- **Reservations / table booking** — this app is order-based (pickup/delivery) only; there is no
  reservation entity, no seating/table concept anywhere in the schema.
- **Inventory / stock management** — no stock counts, no "out of ingredient" auto-disable; the
  only availability signal is the manually-toggled `Dish.isAvailable` boolean.
- **Notifications** (email/SMS/push) — enum exists, nothing sends anything.
- **Persisted SLA alerts** — enum exists, nothing is stored/surfaced beyond a log line.
- **Server-side price recalculation/validation** at order-creation time.
- **Stripe webhooks / payment failure handling** wired to a route.
- **Cash payment confirmation workflow** distinct from card.
- **Order cancellation business rules** (e.g. "can't cancel once PREPARING," refund handling) —
  `CANCELLED` is a reachable enum value but nothing gates who can set it or when, and there's no
  refund integration.
- **Rating range validation** on Reviews.
- **Multi-restaurant-order guard** — nothing currently prevents a single order from containing
  dishes sourced from different restaurants/companies (see domain model doc).
