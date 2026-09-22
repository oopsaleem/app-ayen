# Public Storefront: Visitor Menu Browsing & Ordering

## Context

The application already implements the full SaaS vision for staff-side management: companies, teams, restaurant assignment, kitchens, chef/kitchen assignment, menu management, and the full order fulfillment workflow (dashboard → kitchen prep → chef complete → waiter pickup / rider delivery). `OrderPolicy::create()` already treats "any authenticated user" as a valid customer, and `Order` scoping in `OrderPolicy::view()` already has a fallback branch for a plain customer (`$order->user_id === $user->id`).

The one part of the original vision not yet built: **a visitor to a restaurant's website can browse its menu and place an order without being a staff/team member.** Every existing route (`routes/web.php`, `routes/restaurants.php`) sits behind `auth` + `verified` middleware, so there is currently no way to view a menu, let alone order, without first being a logged-in user.

This spec covers only that gap. It does not change the staff-side management system, the order fulfillment workflow, roles, or permissions.

## Decisions

- **Account required to order.** A visitor can browse a menu without an account, but placing an order still requires being logged in — same as today's `OrderPolicy::create()`. This is the smallest change: no nullable `user_id` on `Order`, no guest-contact schema, and customers get order history/tracking via the existing `orders.index`/`orders.show` pages for free.
- **Slug-based direct link.** Each restaurant gets a public slug (`/r/{slug}`). No platform-wide restaurant directory is being built in this pass — the owner shares the link or a QR code on-premise, consistent with a single-tenant storefront pattern. A directory can be added later without touching this design.
- **Publish gate reuses `RestaurantVerification.verified`.** A restaurant's public page exists only if an admin has verified it (`restaurants/{restaurant}/verify` already exists). No new `is_published` flag.

## Architecture & Routing

Add one new route file, `routes/storefront.php`, entirely outside `auth` middleware:

```php
Route::get('/r/{restaurant:slug}', [StorefrontController::class, 'show'])->name('storefront.show');
```

All existing routes are untouched: `restaurants/{restaurant}/order` (`OrderController@create`), `orders.store`, `orders.index`, `orders.show`, `orders.cancel` keep their current `auth`+`verified` middleware and behavior unchanged.

`StorefrontController@show`:
- Route-model-binds `Restaurant` by `slug`.
- `abort_unless($restaurant->verification?->verified, 404)`.
- Loads the same dish data shape as `OrderController::create` (available dishes, with serving sizes and options), but with no `addresses` prop (visitor isn't authenticated yet) and no `Gate::authorize` call (route is public).
- Renders a new Inertia page, `storefront/show`, read-only browse + cart-building UI.

## Cart & Checkout Hand-off

- The visitor builds a cart client-side on `storefront/show`, persisted to `localStorage` keyed by restaurant id, so it survives navigation and the login redirect.
- "Checkout" button behavior:
  - Not authenticated → redirect to `/login?redirect=/restaurants/{restaurant}/order` (reuses Fortify's existing `redirect` query param handling).
  - Authenticated → navigate straight to `/restaurants/{restaurant}/order`.
- The existing `restaurants/{restaurant}/order` page (`resources/js/pages/restaurants/order.tsx`, rendered by `OrderController::create`) is extended to hydrate its initial cart state from the same `localStorage` key if present, instead of starting empty. Address selection, line editing, and the submit action (`orders.store`) are unchanged.
- On hydration, any cart line whose `dish_id`/`serving_size_id`/`option_ids` no longer resolves against the freshly-loaded dish data (deleted, or `is_available` now `false`) is dropped, with a toast: "Some items were removed because they're no longer available."

This means almost the entire checkout surface (address management, `StoreOrderRequest` validation, `CreateOrder` action, order confirmation, order history) is reused unmodified. The only new UI is the public, read-only menu + cart-building page and the small hydration addition to the existing order page.

## Data Model

One migration: add a unique `slug` column to `restaurants`.

- Generated from `name_en` via `Str::slug()` in `RestaurantController@store` and `@update`, with a numeric suffix on collision (`pizza-palace`, `pizza-palace-2`, ...).
- No changes to `Order`, `OrderPolicy`, `OrderDish`, or any fulfillment-side model.
- No new tables.

## Edge Cases

- Unverified restaurant, or unknown slug → 404 (standard Laravel route-model-binding 404 for unknown slug; explicit `abort_unless` for unverified).
- Cart references a dish removed/made unavailable between add-to-cart and login → dropped on hydration (see above), never reaches `StoreOrderRequest`.
- Empty cart at checkout → already blocked by `StoreOrderRequest`'s `lines` => `required|array|min:1` rule; no new validation needed.
- Restaurant `slug` collision on rename → suffix-and-retry in the controller, not a DB-level surprise.

## Testing

- Feature test: `GET /r/{slug}` for a verified restaurant returns 200 with only `is_available` dishes.
- Feature test: `GET /r/{slug}` for an unverified restaurant returns 404.
- Feature test: `GET /r/{slug}` for an unknown slug returns 404.
- Feature test: `RestaurantController@store`/`@update` sets a unique, non-empty slug, including the collision-suffix case.
- No changes needed to existing `OrderController`/`StoreOrderRequest` tests — checkout behavior is unchanged; cart hydration is client-side only.

## Out of Scope

- Platform-wide restaurant directory/search.
- Guest checkout without an account.
- Payment integration (unchanged from current system — assumed pay-on-delivery/pickup, as today).
- Restaurant "about" pages, hours, ratings, or any content beyond menu browsing.
