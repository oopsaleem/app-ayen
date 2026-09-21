# Yemen Oasis (Laravel rebuild)

A multi-tenant restaurant ordering platform. This context covers Phase 1 (identity/roles and the
Company → Restaurant → Kitchen → Menu domain) and Phase 3 (order creation, pricing, and status
lifecycle — see [ADR-0007](docs/adr/0007-order-pricing-server-authoritative.md) through
[ADR-0012](docs/adr/0012-rider-and-waiter-roles.md)). Payment/Stripe integration, SLA/threshold
alerting, and restaurant-verification gating remain future phases and are not yet part of this
glossary.

## Language

**Company**:
The business entity that owns one or more Restaurants and has Managers. A standalone concept in
this app — distinct from the app's pre-existing generic `Team` concept, which is unrelated tenant
infrastructure this domain does not use.
_Avoid_: Team, Organization, Tenant (as a synonym for Company — "tenant" describes the multi-tenancy
mechanism, not a named domain entity).

**Restaurant**:
A single physical/branded location belonging to a Company. Has one Address, one Verification
record, and one or more Kitchens.
_Avoid_: Branch, Location, Outlet.

**Kitchen**:
A sub-unit of a Restaurant that prepares dishes (e.g. "Grill", "Bakery"). Dishes belong to exactly
one Kitchen, not directly to a Restaurant — a Dish's Restaurant is always derived by walking
Dish → Kitchen → Restaurant. Chefs are assigned to Kitchens, not Restaurants.

**Category**:
A menu category belonging to exactly one Restaurant, supporting self-referential hierarchy
(a Category may have a parent Category). Per-restaurant, not shared platform-wide.

**Dish**:
A menu item belonging to one Category and one Kitchen. Has a base price and optional add-ons:
DishOptions and ServingSizes.

**DishOption**:
An add-on/modifier for a Dish (e.g. "extra cheese"), individually priced and individually
toggleable.

**ServingSize**:
A size/portion variant of a Dish (e.g. small/medium/large), individually priced. Exactly one
ServingSize per Dish is the default.

**Admin**:
A platform-level role held by a User. Verifies Restaurants. Global scope, not tied to a Company.

**Manager**:
A role held by a User, scoped to at most one Company at a time. Manages that Company's
Restaurants and menus.

**Chef**:
A role held by a User, scoped to one or more Kitchens (not to a Restaurant directly).

**Customer**:
A User holding none of the Admin/Manager/Chef roles. Not an explicit role row — presence of no
other role row *is* being a Customer.

**Role**:
A capability a User holds, represented by the presence of a row in a role-specific table
(Admin/Manager/Chef/Rider/Waiter), not by a single field on User. A User may hold more than one
Role at once (e.g. Admin and Manager simultaneously).
_Avoid_: User type, account type — these imply exclusivity, which Role explicitly does not have.

**Verification**:
An Admin's record of whether a Restaurant has been approved. Tracked per Restaurant; in Phase 1 it
does not gate anything (an unverified Restaurant is still fully browsable), and this remains
unenforced in Phase 3 too — an unverified Restaurant's menu is still orderable.

**Order**:
A single food order (pickup or delivery) placed by a Customer against exactly one Restaurant
(ADR-0009) — never a reservation/table booking. Has one or more OrderDishes, a status
(see Status below), and, for delivery orders, a DeliveryAddress. Pricing (`subtotal`/`vat`/
`delivery_fee`/`total`) is computed server-side at creation from current Dish/ServingSize/
DishOption prices, never trusted from the client (ADR-0007).
_Avoid_: Reservation, Booking — this app has no seating/table concept.

**OrderDish**:
One line item on an Order: a Dish (with optional ServingSize and DishOptions selected) plus
quantity, at the price computed at order-creation time. Carries its own `status`
(`pending`/`preparing`/`ready`), set manually by a Chef of the Dish's Kitchen — this is separate
from the Order's own status (see ADR-0010).

**OrderKitchen**:
A join row (per Order, per distinct Kitchen the order touches) recording that a Chef of that
Kitchen has accepted the order's dishes belonging to it. Any one Chef of the Kitchen accepting
sets it — first to act wins, not unanimous. Drives the Order's `PENDING → CONFIRMED` transition
once every OrderKitchen row for the Order is accepted (ADR-0010).

**DeliveryAddress**:
A Customer's saved delivery address (caption, address, lat/lng, default flag) — distinct from a
Restaurant's own Address. An Order in delivery mode references one via `delivery_address_id`.

**Rider**:
A role held by a User, scoped to one Restaurant. Performs the delivery handoff
(`AWAITING_DELIVERY → OUT_FOR_DELIVERY → CLOSED`) on any of that Restaurant's delivery orders —
open-pool, not assigned per-order (ADR-0012).

**Waiter**:
A role held by a User, scoped to one Restaurant. Performs the pickup handoff
(`AWAITING_PICKUP → CLOSED`) on any of that Restaurant's pickup orders — open-pool, same as Rider
(ADR-0012).
