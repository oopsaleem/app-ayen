# Domain Model

Conceptual entities, fields, and relationships. Field names are given as a reference vocabulary,
not a schema to copy verbatim. "PK"/"FK" below mean primary/foreign key in the *conceptual* sense.

## Identity

### User
The shared identity record every actor has, regardless of role.
- `id` (PK) — opaque external identity id (currently a Firebase-style uid string, i.e. identity
  is managed by an external auth provider, not sequential integers).
- `name`, `image` — optional profile fields.
- `createdAt`, `updatedAt`.
- Has at most one of each: Credentials, AuthProvider, Admin, Manager, Chef, Customer row.

### Credentials
Email/password login data for a User.
- `userId` (PK, FK → User, 1:1)
- `email` (unique)
- `passwordHash`

### AuthProvider
Which external identity method a User authenticated with.
- `userId` (PK, FK → User, 1:1)
- `type` — enum: `GOOGLE`, `CREDENTIALS`

### Admin / Manager / Chef / Customer
Each is a **role table**, not a `role` enum column on User — a role is *presence of a row*.
- `userId` (PK, FK → User, 1:1)
- `createdAt`, `updatedAt`
- Manager additionally has: `displayName`, `companyId` (FK → Company, nullable — a manager can
  exist unassigned to a company)
- Chef additionally has: `displayName`
- Customer additionally has: `displayName`
- A single underlying User **can** hold multiple role rows simultaneously (e.g. Admin + Manager).
  Authorization logic must aggregate roles across all role tables for a user, not assume
  exclusivity.
- There is no explicit `CUSTOMER` role check anywhere in authorization — a user with none of
  Admin/Manager/Chef is treated as an ordinary user/customer by omission, not by an explicit role
  row check.

## Organization

### Company
- `id` (PK)
- `displayName`, `description`
- `createdAt`, `updatedAt`
- Has many Managers, many Restaurants.

### Restaurant
- `id` (PK)
- `companyId` (FK → Company)
- `nameEn`, `nameAr`, `descriptionEn`, `descriptionAr`
- `lightImages: string[]`, `darkImages: string[]` — theme-specific image sets (not a single image
  list re-themed at render time)
- Has exactly one Address (1:1), at most one Verification (1:1), many Kitchens, many Reviews, many
  OrderStatusThresholds (restaurant-specific overrides).

### Address (restaurant address)
- `id` (PK)
- `restaurantId` (FK → Restaurant, **unique** — enforces 1:1)
- `address` (text), `lat`, `lng`
- Indexed on `(lat, lng)` for geospatial queries — geospatial search is anticipated but not
  observed as implemented in current resolvers/services.

### Verification
- `restaurantId` (PK, FK → Restaurant, 1:1)
- `adminId` (FK → Admin) — which admin performed/owns the verification
- `verified: boolean` (default false)
- **Note:** modeled as "does a verification record exist / is it flagged true," but nothing in
  the order-placement or menu-visibility logic currently checks this flag. In a faithful rebuild,
  decide explicitly whether unverified restaurants should be orderable (current behavior: yes,
  the flag is not enforced).

### Kitchen
- `id` (PK)
- `restaurantId` (FK → Restaurant)
- `nameEn`, `nameAr`
- Has many Dishes, many ChefKitchen links.
- **A restaurant's kitchens are the unit dishes are actually organized by**; a Dish does not
  reference Restaurant directly — it references Kitchen, and Kitchen references Restaurant. Any
  "which restaurant does this dish belong to" query must join through Kitchen.

### ChefKitchen (join entity)
Many-to-many between Chef and Kitchen — a chef can work in multiple kitchens; a kitchen can have
multiple chefs.
- `chefId` (FK → Chef), `kitchenId` (FK → Kitchen), composite PK `(chefId, kitchenId)`.
- Cascade delete in both directions (removing a Chef or Kitchen removes the link rows).

## Menu

### Category
- `id` (PK)
- `nameEn`, `nameAr`, `descriptionEn`, `descriptionAr`
- `lightImage`, `darkImage` — single optional image per theme (unlike Restaurant/Dish which use
  arrays)
- `isActive: boolean` (default true) — soft visibility toggle
- `level: int` (default 1) and `parentId` (FK → Category, nullable, self-relation) — categories
  form a **tree**; `level` tracks depth (1 = top-level) and is maintained by the *application*,
  not derived automatically from `parentId` by the database.
- `order: int` (default 0) — manual sort order among siblings.
- **Categories are global, not per-restaurant.** There is no `restaurantId` on Category. Every
  restaurant draws from the same category tree; a dish's category does not imply which
  restaurant(s) offer it — that comes from the dish's kitchen instead. A rebuild should decide
  deliberately whether to keep categories global (current behavior) or scope them per restaurant.

### Dish
- `id` (PK)
- `categoryId` (FK → Category), `kitchenId` (FK → Kitchen)
- `nameEn`, `nameAr`, `descriptionEn`, `descriptionAr`
- `price: float` — base price
- `isAvailable: boolean` (default true) — soft toggle for temporarily 86'ing an item
- `lightImages: string[]`, `darkImages: string[]`
- Has many DishOptions, many ServingSizes, many OrderDishes (order history, not current cart).

### DishOption
An add-on/modifier for a dish (e.g. "extra cheese").
- `id` (PK), `dishId` (FK → Dish)
- `nameEn`, `nameAr`, `price: float`
- Multiple options can be selected per order line (see OrderDishOption). No `isAvailable` flag —
  options can't be individually disabled without deleting them.

### ServingSize
A size/portion variant of a dish (e.g. small/medium/large, or "serves 4").
- `id` (PK), `dishId` (FK → Dish)
- `nameEn`, `nameAr`, `price: float`
- `isDefault: boolean` (default false) — should be exactly one true per dish, **not enforced by a
  database constraint**; enforcing "exactly one default serving size per dish" is application
  logic to (re)implement.
- `servingsCount: int` (default 1) — how many portions this size yields (e.g. for family-style
  dishes).

## Ordering

### DeliveryAddress (customer's saved address — distinct from Restaurant's Address)
- `id` (PK), `customerId` (FK → Customer)
- `caption` (optional label, e.g. "Home"), `address`, `lat`, `lng`
- `isDefault: boolean` (default false), `isActive: boolean` (default true) — a customer can have
  multiple saved addresses; "default" and "active/archived" are independent flags, not enforced
  as mutually-exclusive-default by the database.
- Has many Orders (an order references the DeliveryAddress used, if any).

### Order
- `id` (PK), `customerId` (FK → Customer), `deliveryAddressId` (FK → DeliveryAddress, **nullable**
  — null for pickup orders)
- `status` — enum, see Order Status Lifecycle below. Default `PENDING`.
- `deliveryMode` — enum `PICKUP` | `DELIVERY`. Default `PICKUP`.
- `paymentMethod` — enum `CARD` | `CASH`. Default `CARD`.
- `paidAt` (nullable timestamp), `paymentReference` (nullable — Stripe session id), `paymentError`
  (nullable string — set when a payment attempt fails)
- `total`, `vat`, `delivery` (delivery fee, default 0), `paymentFee` (card processing fee, default
  0) — all floats. **These are computed client-side and submitted as part of order creation; the
  server does not independently recompute or validate them against dish prices** (see
  implementation guide — this is a gap to fix, not preserve).
- Status timestamps, one per lifecycle stage, all nullable except `pendingAt` (defaults to
  creation time): `pendingAt`, `confirmedAt`, `preparingAt`, `readyAt`, `deliveringAt`,
  `cancelledAt`, `closedAt`. Set as a side effect of a status transition (see Features doc).
- Has many OrderDishes, many OrderStatusHistory entries.
- **An order is not scoped to a single restaurant at the schema level** — it holds a flat list of
  OrderDishes, each pointing at a Dish, and a Dish belongs to exactly one Kitchen/Restaurant.
  Nothing prevents an order from containing dishes from *different* restaurants/companies in the
  current schema. Whether this is intentional (a cart that spans restaurants) or an oversight is
  unclear from the code; a rebuild should make an explicit decision and, if single-restaurant
  orders are intended, enforce it (e.g. validate all OrderDishes' dishes share one restaurant at
  order-creation time).

### OrderDish (order line item)
A snapshot of what was ordered, decoupled from the live Dish record so later price/name changes
don't retroactively alter historical orders.
- `id` (PK), `orderId` (FK → Order), `dishId` (FK → Dish, for reference/reporting only)
- `servingsNameEn`, `servingsNameAr` — snapshot of the chosen serving size's name
- `unitPrice: float` — snapshot of the chosen serving size's price at order time
- `quantity: int` (default 1)
- `totalPrice: float` — should equal `quantity * (unitPrice + sum(options.price))`; **computed and
  submitted by the client, not recalculated/validated by the server.**
- `notes` (optional customer instructions, e.g. "no onions")
- `isReady: boolean` (default false) — per-line kitchen-completion flag, independent of the
  order-level status; lets a kitchen mark individual items done while the order is still
  "preparing" overall. Nothing currently rolls this up into an automatic order-status change
  when all lines become ready — that aggregation is not implemented.
- Has many OrderDishOptions.

### OrderDishOption
A snapshot of a chosen DishOption at order time.
- `id` (PK), `orderDishId` (FK → OrderDish)
- `nameEn`, `nameAr`, `price: float`

### OrderStatusHistory
Audit log of every status transition an order goes through.
- `id` (PK), `orderId` (FK → Order)
- `status` — the status being entered
- `duration: int?` — minutes spent in the *previous* status before this transition (0 for the
  first entry, since there is no previous status)
- `note` (optional, currently always written as an empty string by the automatic status-change
  path — manual notes are supported by the schema but not populated by any current code path)
- `changedBy` (optional — uid of the user who triggered the change)
- `createdAt`

### OrderStatusThreshold
Configurable SLA: the maximum time (minutes) an order should spend in a given status before it's
considered breached.
- `id` (PK)
- `status` — which OrderStatus this threshold applies to
- `maxDuration: int` (minutes)
- `isActive: boolean` (default true)
- `restaurantId` (FK → Restaurant, **nullable**) — null means a **global default** threshold for
  that status; a restaurant-specific row overrides the global one for that restaurant.
- Uniqueness rule: at most one *active* threshold per `(status, restaurantId)` pair — enforced in
  application code at create time (not a DB unique constraint on `isActive`, so toggling
  `isActive` off then creating a new one is how you "replace" a threshold).

## Feedback

### Review
- `id` (PK), `customerId` (FK → Customer), `restaurantId` (FK → Restaurant)
- `rating: int` (default 0 — **no enforced range**, e.g. nothing stops a rating of 999 or -5;
  a rebuild should constrain this, e.g. 1–5)
- `comment` (optional)
- Reviews are against a **restaurant**, not an order or a specific dish. There is no concept of
  "verified purchase" linking a review to an order, and no restriction preventing multiple
  reviews from the same customer for the same restaurant.

## Enumerations

| Enum | Values | Used by |
|---|---|---|
| `AuthProviderType` | `GOOGLE`, `CREDENTIALS` | AuthProvider |
| `PaymentMethod` | `CARD`, `CASH` | Order |
| `OrderStatus` | `PENDING`, `CONFIRMED`, `PREPARING`, `READY`, `DELIVERING`, `CLOSED`, `CANCELLED` | Order, OrderStatusHistory, OrderStatusThreshold |
| `DeliveryMode` | `PICKUP`, `DELIVERY` | Order |
| `UserRole` | `ADMIN`, `MANAGER`, `CHEF`, `CUSTOMER` | Defined but not actually used as a stored field anywhere — role is derived from row presence in the Admin/Manager/Chef tables (see Identity section). Treat this enum as documentation of the conceptual role set, not a column to reproduce literally. |
| `AlertType` | `APPROACHING`, `BREACHED` | Defined in schema but **not referenced by any current model or service** — anticipated for threshold-breach alerting, not implemented. |
| `NotificationType` | `EMAIL`, `SMS`, `PUSH` | Defined in schema but **not referenced anywhere else** — no notification system is implemented. |
| `Language` | `EN`, `AR` | Defined in schema but **not used as a stored field anywhere** — bilingual content is instead modeled as parallel `xxxEn`/`xxxAr` fields per entity, not via a language-tagged row/table. |

## Entity relationship summary

```
Company 1──* Restaurant 1──1 Address
Company 1──* Manager (0..1 per manager)
Restaurant 1──1 Verification (by Admin)
Restaurant 1──* Kitchen 1──* Dish *──1 Category (Category is self-hierarchical, global)
Kitchen *──* Chef  (via ChefKitchen)
Dish 1──* DishOption
Dish 1──* ServingSize
Customer 1──* DeliveryAddress
Customer 1──* Order *──1 DeliveryAddress (nullable, for delivery orders)
Order 1──* OrderDish *──1 Dish (snapshotted)
OrderDish 1──* OrderDishOption (snapshotted)
Order 1──* OrderStatusHistory
Restaurant 1──* OrderStatusThreshold (0..1 global fallback with restaurantId = null)
Customer 1──* Review *──1 Restaurant
User 1──1 Credentials, 1──1 AuthProvider, 1──(0..1)── each of Admin/Manager/Chef/Customer
```
