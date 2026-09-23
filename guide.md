# App Guide

Multi-tenant restaurant ordering platform. A **Company** owns **Restaurants**; each restaurant
has **Kitchens** that prepare a menu; customers order for pickup or delivery; staff move each
order through a status pipeline. Local URL: `https://app.test`.

## 1. Roles

| Role | Scoped to | Does |
|---|---|---|
| Admin | Platform-wide | Verifies restaurants |
| Manager | One Company | Manages that company's restaurants and menus |
| Chef | One or more Kitchens | Prepares dishes, accepts orders for their kitchen |
| Waiter | One Restaurant | Hands off pickup orders (open pool, any waiter can act) |
| Rider | One Restaurant | Hands off delivery orders (open pool) |
| Customer | Self | No role row at all — anyone with none of the above is a customer |

A single user can hold multiple roles at once (e.g. Manager + Chef). Separately, every user also
belongs to a **Team** (owner/admin/member) — this is generic account/workspace infrastructure,
unrelated to the Company/Restaurant domain above; don't confuse the two.

## 2. Menu hierarchy

```
Company → Restaurant → Kitchen → Dish → (ServingSize | DishOption)
                     → Category (own hierarchy, can nest)
```

A dish belongs to one kitchen and one category. It can have serving sizes (small/medium/large,
one marked default) and options (add-ons like extra toppings), each individually priced.

## 3. Setting up a restaurant (staff flow)

### 3.0 First-time setup: creating the first Admin

There's no default admin account. The very first time the app runs (no `Admin` row exists yet),
visit **`/setup/admin`** — an open, no-login-required form that creates the platform's first
admin account and logs you in. Once an Admin exists, that same URL locks down: it requires you to
already be logged in as an Admin *and* re-confirm your password (Laravel's standard
password-confirmation screen) before it lets you create another one.

Assigning **Manager**, **Chef**, **Waiter**, or **Rider** to a user still has no UI — those role
rows have to be created directly, e.g. via `php artisan tinker`:

```
php artisan tinker --execute 'App\Models\Manager::create(["user_id" => 2, "company_id" => 1, "display_name" => "Jane"]);'
```

### 3.1 Company → restaurant → menu

1. As an Admin, create a company: `/companies` → "New Company"
2. From the company's edit page, click "Add Restaurant" (this is the only place restaurant
   creation is reachable from — the `/restaurants` list page has no create button of its own)
3. Add kitchens, categories, and dishes from the restaurant's edit page (`/restaurants/{restaurant}/edit`)
4. As an Admin, verify the restaurant: `POST /restaurants/{restaurant}/verify`

Note: verification is tracked but not currently enforced — an unverified restaurant's storefront
and ordering still work.

## 4. Customer ordering flow

1. Customer visits the public storefront: `GET /r/{restaurant-slug}` — no login required to browse
2. They build a cart client-side (no server-side cart) and place the order: `restaurants/{restaurant}/order` → `POST /orders`
3. Pricing (subtotal/VAT/delivery fee/total) is always computed server-side from current
   dish/serving-size/option prices, never trusted from the client
4. Customer tracks their orders at `/orders` and `/orders/{order}`
5. Customer can cancel while the order is still cancellable: `POST /orders/{order}/cancel`

## 5. Order lifecycle

```
pending → confirmed → preparing → awaiting_delivery → out_for_delivery → closed
                                 → awaiting_pickup    → closed
   ↓            ↓          ↓
 cancelled  cancelled  cancelled
```

- **pending → confirmed**: automatic, once every kitchen touched by the order has a chef accept it
  (first chef of that kitchen to act wins — not unanimous across chefs)
- **preparing → awaiting_delivery/awaiting_pickup**: automatic, once every dish in the order is
  marked `ready`
- **awaiting_delivery → out_for_delivery → closed**: any Rider of the restaurant (open pool)
- **awaiting_pickup → closed**: any Waiter of the restaurant (open pool)

Each dish within an order has its own status (`pending → preparing → ready`), set by a chef of
that dish's kitchen — separate from the order's overall status.

## 6. Staff order screens

| Role | Screen | Action |
|---|---|---|
| Chef | `/chef/kitchens`, `/chef/orders` | Accept order for a kitchen; advance dish status |
| Waiter | `/waiter/orders` | Hand off ready pickup orders |
| Rider | `/rider/orders` | Pick up, then deliver, ready delivery orders |

## 7. Account & team settings

- Profile: `/settings/profile`
- Security (password, 2FA, passkeys): `/settings/security`
- Teams (generic workspaces, not the restaurant domain): `/settings/teams` — invite members,
  assign owner/admin/member roles, switch active team
- Delivery addresses: `/addresses`
- Language: `POST /locale` (English/Arabic; most content fields store both `_en` and `_ar`
  variants directly rather than a translation table)

## 8. Where things live in code

- Domain glossary and design decisions: [`CONTEXT.md`](CONTEXT.md) and [`docs/adr/`](docs/adr)
- Controllers grouped by domain: `app/Http/Controllers/{Companies,Restaurants,Kitchens,Menu,Orders,Chef,Waiter,Rider,Teams,Settings}`
- Status/role rules: `app/Enums/{OrderStatus,OrderDishStatus,DeliveryMode,TeamRole}.php`
- Frontend pages (Inertia + React): `resources/js/pages/**`

Next: run `php artisan route:list` for the full, current route table if a URL above ever changes.

## 9. Local dev setup

The site is served by **Laravel Herd** at `https://app.test` — never run `php artisan serve` or
similar, it's always running.

1. Install dependencies and prep the environment (one-time): `composer run setup`
   — copies `.env`, generates the app key, runs migrations, installs npm packages, builds assets
2. Start the dev processes (server logs, queue worker, and Vite together): `composer run dev`
3. If you only need Vite's hot-reload for frontend changes without the rest: `npm run dev`
4. Database is SQLite by default (`database/database.sqlite`) — no separate DB server needed

Common commands:

| Task | Command |
|---|---|
| Run tests | `php artisan test --compact` |
| Fix PHP style | `vendor/bin/pint --dirty --format agent` |
| Fix JS/TS lint + format | `npm run lint`, `npm run format` |
| Type-check PHP | `composer run types:check` |
| Type-check TS | `npm run types:check` |
| Full CI check locally | `composer run ci:check` |

If a frontend change isn't showing up in the browser, it means Vite isn't running or hasn't
rebuilt — start `composer run dev` or `npm run dev`.
