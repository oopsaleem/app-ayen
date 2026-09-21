# Overview

## What the app is

Yemen Oasis is a **multi-tenant restaurant ordering platform**. A business ("Company") can own
multiple restaurant locations. Each restaurant publishes a menu (organized into kitchens →
categories → dishes) that customers browse, order from (pickup or delivery), and pay for online
(Stripe) or by marking the order as cash. Restaurant staff track and advance each order through a
kitchen workflow (pending → confirmed → preparing → ready → delivering → closed, or cancelled),
and the system measures how long orders spend in each stage against configurable time thresholds
so slow kitchens/statuses can be flagged.

The product supports **two languages** (English and Arabic) as first-class data — most
content-bearing fields (names, descriptions) are stored as an `En`/`Ar` pair rather than through a
generic translation table. It also supports **light/dark mode image variants** for
restaurants/dishes/categories (two separate image arrays, not a single set with theming applied at
render time).

## Actors / roles

| Role | Identity model | Scope | Purpose |
|---|---|---|---|
| **Customer** | `Customer` | Self (their own orders, addresses, reviews) | Browses menus, places orders, manages delivery addresses, leaves reviews. |
| **Chef** | `Chef` | One or more `Kitchen`s (via a many-to-many link) | Prepares dishes; scoped to specific kitchens within a restaurant. |
| **Manager** | `Manager` | One `Company` (and therefore all of that company's restaurants) | Manages a company's restaurants, menus, and views/progresses orders placed against that company's restaurants. |
| **Admin** | `Admin` | Global | Platform-level: verifies restaurants, manages other admins. Has row-level override on ownership checks. |

Every actor is first a generic `User` (a shared identity record); the specific role
(`Admin`/`Manager`/`Chef`/`Customer`) is a **separate table keyed by the same id**, not a `type`
column. A single `User` can simultaneously hold more than one role row (e.g. be both an Admin and
a Manager) — the current authorization logic actually assumes this is possible and merges roles
into a single list per request. **`CUSTOMER` is not tracked as an explicit role for
authorization purposes** — a user with no Admin/Manager/Chef row is implicitly treated as an
ordinary customer/end user.

## Core concepts

- **Company** — the business entity. Owns restaurants. Has managers.
- **Restaurant** — a single physical/branded location belonging to a company. Has one address,
  one verification record, and one or more kitchens. Must be verified by an admin before it
  should be considered live (verification is modeled but not enforced anywhere in the order flow
  today — see features doc).
- **Kitchen** — a sub-unit of a restaurant that prepares certain dishes (e.g. "Grill", "Bakery").
  Dishes belong to exactly one kitchen. Chefs are assigned to kitchens, not restaurants directly.
  A restaurant's identity for authorization purposes is derived by walking
  `Dish → Kitchen → Restaurant → Company`.
- **Category** — a menu category, supporting **self-referential hierarchy** (a category can have
  a parent category, enabling category → subcategory nesting) and manual ordering.
- **Dish** — a menu item. Has a base price, availability flag, and optional add-ons:
  **Options** (e.g. extra toppings, each with its own price) and **Serving Sizes** (e.g.
  small/medium/large, each with its own price and a `servingsCount`, with one marked default).
- **Order** — a customer's food order against one restaurant's dishes (in practice, against one or
  more dishes that may span kitchens/restaurants — see domain model for the multi-restaurant
  order gap). Has a delivery mode (pickup or delivery), payment method (card or cash), a status
  that progresses through a fixed lifecycle, and captures a timestamp for every stage transition.
- **OrderDish** — a line item within an order: a snapshot of a dish's serving size and price *at
  order time* (unaffected by later price changes to the Dish), quantity, notes, and a per-item
  `isReady` flag so the kitchen can track partial completion.
- **OrderDishOption** — a snapshot of a chosen dish option's name/price at order time, attached to
  an OrderDish.
- **OrderStatusHistory** — an audit trail: one row per status transition, with the duration (in
  minutes) spent in the *previous* status.
- **OrderStatusThreshold** — a configured maximum duration (minutes) allowed for an order to
  remain in a given status, optionally restaurant-specific (falls back to a global default when no
  restaurant-specific threshold exists). Used to detect/flag orders that are taking too long.
- **DeliveryAddress** — a customer's saved address (with lat/lng for geospatial use), which can be
  marked default/active and is attached to delivery orders.
- **Review** — a customer's rating + comment against a restaurant (not against an order or a
  specific dish).
- **Verification** — an admin's sign-off that a given restaurant is legitimate/approved.

## What "partially implemented" means here

The schema and CRUD scaffolding exist for essentially every entity above (create/read/update/
delete, both as GraphQL resolvers and REST controllers, generated from the same Prisma models).
The **real, hand-written business logic** — as opposed to generated CRUD — is concentrated in a
small number of places:

- Order creation and status-transition side effects (`OrdersService`)
- Order status duration tracking and threshold-breach detection
  (`OrderStatusHistoriesService`, `OrderStatusThresholdsService`)
- Row-level authorization (customer-owns-order, manager-owns-company's-restaurant) in the orders
  resolver
- Stripe checkout session creation and a redirect-based "success" handler

Everything else (addresses, categories, chefs, companies, dishes, kitchens, managers, reviews,
serving sizes, verifications) is generic CRUD with almost no domain-specific validation beyond
"does the referenced row exist." See [02-features.md](./02-features.md) for the precise
done/partial/missing breakdown, and [03-implementation-guide.md](./03-implementation-guide.md) for
gaps that should be **fixed**, not faithfully reproduced, in a rebuild.
