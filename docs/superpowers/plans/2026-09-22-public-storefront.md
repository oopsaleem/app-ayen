# Public Storefront Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an unauthenticated visitor browse a verified restaurant's menu at a public, slug-based URL and build a cart, then hand off to the existing authenticated checkout flow to place the order.

**Architecture:** One new backend route/controller renders a read-only public menu page for a restaurant, gated on `RestaurantVerification.verified`. The frontend builds a cart client-side (new shared cart library + a shared per-dish selector component reused by both the new public page and the existing authenticated order page), persists it to `localStorage` across the login redirect, and hydrates it back into the existing, unmodified checkout page (`orders.store` action, `StoreOrderRequest` validation, `CreateOrder` action are all untouched).

**Tech Stack:** Laravel 13 / PHP 8.4, Inertia v3 + React, Pest, Laravel Wayfinder (typed route/action generation), i18next (en/ar).

**Spec:** `docs/superpowers/specs/2026-09-22-public-storefront-design.md`

## Global Constraints

- Account required to order — no guest checkout, no nullable `user_id` on `Order` (per spec's "Decisions").
- Public menu reachable only for a restaurant with `RestaurantVerification.verified === true` (per spec's "Decisions").
- No changes to `Order`, `OrderPolicy`, `OrderDish`, `StoreOrderRequest`, `CreateOrder`, or any fulfillment-side model/controller (per spec's "Architecture & Routing").
- Migrations in this codebase never reference Eloquent models (existing convention — no `database/migrations/*.php` file does); backfills use `DB::table()`.
- All PHP files touched must be run through `vendor/bin/pint --dirty --format agent` before the task's commit.
- All new/changed frontend strings go in `resources/js/i18n/locales/en.json` and `resources/js/i18n/locales/ar.json` — no hardcoded UI strings.
- This project has no JS unit test runner (no vitest/jest in `package.json`) — frontend logic is verified via Pest feature tests on the props the backend renders, plus manual browser verification (Task 7). This matches the existing test suite's approach; do not add a new test framework.

---

### Task 1: Restaurant slug

**Files:**
- Create: `database/migrations/2026_09_22_120000_add_slug_to_restaurants_table.php`
- Modify: `app/Models/Restaurant.php`
- Test: `tests/Feature/Catalog/RestaurantTest.php`

**Interfaces:**
- Produces: `Restaurant::$slug` (string, unique, non-null after this task), auto-generated from `name_en` on create, regenerated on update whenever `name_en` changes. `Restaurant` becomes route-bindable by slug via Laravel's `{restaurant:slug}` route syntax (used starting Task 2).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Catalog/RestaurantTest.php`:

```php
test('a restaurant is created with a slug derived from its english name', function () {
    $restaurant = Restaurant::factory()->create(['name_en' => 'Pizza Palace']);

    expect($restaurant->slug)->toBe('pizza-palace');
});

test('a restaurant slug uses the next available suffix on collision', function () {
    Restaurant::factory()->create(['name_en' => 'Pizza Palace', 'slug' => 'pizza-palace']);
    Restaurant::factory()->create(['name_en' => 'Pizza Palace', 'slug' => 'pizza-palace-1']);

    $restaurant = Restaurant::factory()->create(['name_en' => 'Pizza Palace']);

    expect($restaurant->slug)->toBe('pizza-palace-2');
});

test('renaming a restaurant regenerates its slug', function () {
    $restaurant = Restaurant::factory()->create(['name_en' => 'Pizza Palace']);

    $restaurant->update(['name_en' => 'Pasta Place']);

    expect($restaurant->fresh()->slug)->toBe('pasta-place');
});

test('updating a restaurant without changing its name keeps the existing slug', function () {
    $restaurant = Restaurant::factory()->create(['name_en' => 'Pizza Palace']);

    $restaurant->update(['description_en' => 'Updated description']);

    expect($restaurant->fresh()->slug)->toBe('pizza-palace');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=RestaurantTest`
Expected: FAIL — `slug` column/attribute does not exist yet.

- [ ] **Step 3: Create the migration**

`database/migrations/2026_09_22_120000_add_slug_to_restaurants_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('company_id');
        });

        $this->backfillSlugs();

        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }

    /**
     * Assign a unique slug to every restaurant created before this column
     * existed, so the not-null constraint above never fails on real data.
     */
    private function backfillSlugs(): void
    {
        $usedSlugs = [];

        DB::table('restaurants')->orderBy('id')->get(['id', 'name_en'])
            ->each(function (object $restaurant) use (&$usedSlugs) {
                $base = Str::slug($restaurant->name_en) ?: 'restaurant';
                $slug = $base;
                $suffix = 1;

                while (in_array($slug, $usedSlugs, true)) {
                    $slug = $base.'-'.$suffix;
                    $suffix++;
                }

                $usedSlugs[] = $slug;

                DB::table('restaurants')->where('id', $restaurant->id)->update(['slug' => $slug]);
            });
    }
};
```

- [ ] **Step 4: Add slug generation to the `Restaurant` model**

Modify `app/Models/Restaurant.php`. In the `use` block, add `use Illuminate\Support\Str;` (alphabetically, after `use Illuminate\Database\Eloquent\Relations\HasOne;` and before `use Illuminate\Support\Carbon;`).

Change:

```php
 * @property int $company_id
 * @property string $name_en
```

to:

```php
 * @property int $company_id
 * @property string $slug
 * @property string $name_en
```

Change:

```php
#[Fillable(['company_id', 'name_en', 'name_ar', 'description_en', 'description_ar', 'images'])]
```

to:

```php
#[Fillable(['company_id', 'slug', 'name_en', 'name_ar', 'description_en', 'description_ar', 'images'])]
```

Then, as the first method inside the class body (before `company()`), add:

```php
    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Restaurant $restaurant): void {
            if (empty($restaurant->slug)) {
                $restaurant->slug = static::generateUniqueSlug($restaurant->name_en);
            }
        });

        static::updating(function (Restaurant $restaurant): void {
            if ($restaurant->isDirty('name_en')) {
                $restaurant->slug = static::generateUniqueSlug($restaurant->name_en, $restaurant->id);
            }
        });
    }

    /**
     * Generate a unique slug for the restaurant, matching the suffixing
     * scheme Team uses for its own slugs.
     */
    protected static function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $defaultSlug = Str::slug($name);

        $query = static::query()
            ->where(function ($query) use ($defaultSlug) {
                $query->where('slug', $defaultSlug)
                    ->orWhere('slug', 'like', $defaultSlug.'-%');
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $existingSlugs = $query->pluck('slug');

        $maxSuffix = $existingSlugs
            ->map(function (string $slug) use ($defaultSlug): ?int {
                if ($slug === $defaultSlug) {
                    return 0;
                } elseif (preg_match('/^'.preg_quote($defaultSlug, '/').'-(\d+)$/', $slug, $matches)) {
                    return (int) $matches[1];
                }

                return null;
            })
            ->filter(fn (?int $suffix) => $suffix !== null)
            ->max() ?? 0;

        return $existingSlugs->isEmpty()
            ? $defaultSlug
            : $defaultSlug.'-'.($maxSuffix + 1);
    }
```

This mirrors `App\Concerns\GeneratesUniqueTeamSlugs` (used by `Team`), minus `withTrashed()` — `Restaurant` has no `SoftDeletes`, so it isn't reused as a shared trait.

- [ ] **Step 5: Run migration and tests**

Run: `php artisan migrate`
Run: `php artisan test --compact --filter=RestaurantTest`
Expected: PASS

- [ ] **Step 6: Format and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add database/migrations/2026_09_22_120000_add_slug_to_restaurants_table.php app/Models/Restaurant.php tests/Feature/Catalog/RestaurantTest.php
git commit -m "feat: add unique slug to restaurants"
```

---

### Task 2: Public storefront route and controller

**Files:**
- Create: `routes/storefront.php`
- Modify: `routes/web.php`
- Create: `app/Http/Controllers/Storefront/StorefrontController.php`
- Test: `tests/Feature/Storefront/StorefrontTest.php`

**Interfaces:**
- Consumes: `Restaurant::$slug` (Task 1), `Restaurant::verification()->verified` (existing), the dish-loading query from `OrderController::create` (existing, copied verbatim).
- Produces: named route `storefront.show` at `GET /r/{restaurant:slug}` (no auth middleware). Renders Inertia component `storefront/show` with props:
  - `restaurant: { id: number, name_en: string, description_en: string | null }`
  - `dishes: Array<{ id: number, name_en: string, name_ar: string, description_en: string | null, price: string, serving_sizes: Array<{id, name_en, name_ar, price}>, options: Array<{id, name_en, name_ar, price}> }>`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Storefront/StorefrontTest.php`:

```php
<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\Restaurant;
use App\Models\RestaurantVerification;

/**
 * Create a dish belonging to exactly one restaurant.
 */
function dishFor(Restaurant $restaurant, ?string $price = null): Dish
{
    return Dish::factory()
        ->for(Category::factory()->for($restaurant, 'restaurant'), 'category')
        ->for(Kitchen::factory()->for($restaurant, 'restaurant'), 'kitchen')
        ->create($price !== null ? ['price' => $price] : []);
}

test('a visitor can view the public menu of a verified restaurant without logging in', function () {
    $restaurant = Restaurant::factory()
        ->has(RestaurantVerification::factory()->state(['verified' => true]), 'verification')
        ->create(['name_en' => 'Pizza Palace']);
    $dish = dishFor($restaurant, price: '10.00');

    $this->get("/r/{$restaurant->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/show')
            ->where('restaurant.name_en', 'Pizza Palace')
            ->has('dishes', 1)
            ->where('dishes.0.id', $dish->id));
});

test('unavailable dishes are excluded from the public menu', function () {
    $restaurant = Restaurant::factory()
        ->has(RestaurantVerification::factory()->state(['verified' => true]), 'verification')
        ->create();
    dishFor($restaurant);
    Dish::factory()
        ->for(Category::factory()->for($restaurant, 'restaurant'), 'category')
        ->for(Kitchen::factory()->for($restaurant, 'restaurant'), 'kitchen')
        ->create(['is_available' => false]);

    $this->get("/r/{$restaurant->slug}")
        ->assertInertia(fn ($page) => $page->has('dishes', 1));
});

test('a restaurant explicitly marked unverified has no public menu', function () {
    $restaurant = Restaurant::factory()
        ->has(RestaurantVerification::factory()->state(['verified' => false]), 'verification')
        ->create();

    $this->get("/r/{$restaurant->slug}")->assertNotFound();
});

test('a restaurant with no verification record at all has no public menu', function () {
    $restaurant = Restaurant::factory()->create();

    $this->get("/r/{$restaurant->slug}")->assertNotFound();
});

test('an unknown storefront slug returns not found', function () {
    $this->get('/r/does-not-exist')->assertNotFound();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=StorefrontTest`
Expected: FAIL — route `storefront.show` does not exist (404 with no route, or `RouteNotFoundException` if referenced by name).

- [ ] **Step 3: Create the controller**

`app/Http/Controllers/Storefront/StorefrontController.php`:

```php
<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\Restaurant;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    /**
     * Render the public menu for a verified restaurant, so a visitor can
     * browse and build a cart without an account.
     */
    public function show(Restaurant $restaurant): Response
    {
        abort_unless($restaurant->verification?->verified, 404);

        $dishes = Dish::query()
            ->whereHas('kitchen', fn ($query) => $query->where('restaurant_id', $restaurant->id))
            ->where('is_available', true)
            ->with([
                'servingSizes' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name_en'),
                'options' => fn ($query) => $query->where('is_available', true)->orderBy('name_en'),
            ])
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ar', 'description_en', 'price']);

        return Inertia::render('storefront/show', [
            'restaurant' => $restaurant->only(['id', 'name_en', 'description_en']),
            'dishes' => $dishes,
        ]);
    }
}
```

- [ ] **Step 4: Register the route**

Create `routes/storefront.php`:

```php
<?php

use App\Http\Controllers\Storefront\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/r/{restaurant:slug}', [StorefrontController::class, 'show'])->name('storefront.show');
```

In `routes/web.php`, after the existing `require __DIR__.'/restaurants.php';` line, add:

```php
require __DIR__.'/storefront.php';
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=StorefrontTest`
Expected: PASS

- [ ] **Step 6: Format and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Http/Controllers/Storefront/StorefrontController.php routes/storefront.php routes/web.php tests/Feature/Storefront/StorefrontTest.php
git commit -m "feat: add public storefront route for verified restaurants"
```

---

### Task 3: Shared cart library

**Files:**
- Create: `resources/js/lib/order-cart.ts`

**Interfaces:**
- Produces:
  - Types: `ServingSize`, `DishOption`, `Dish`, `Line`.
  - `lineFor(lines: Record<number, Line>, dishId: number): Line`
  - `unitPriceFor(dish: Dish, line: Line): number`
  - `readStoredCart(restaurantId: number, dishes: Dish[]): { lines: Record<number, Line>; removedCount: number }` — reads and clears the `localStorage` entry for `restaurantId`, dropping any line whose dish/serving-size/option no longer matches `dishes`.
  - `writeStoredCart(restaurantId: number, lines: Record<number, Line>): void` — writes non-empty lines to `localStorage`; clears the entry if the cart is empty.

- [ ] **Step 1: Create the library**

`resources/js/lib/order-cart.ts`:

```typescript
export type ServingSize = {
    id: number;
    name_en: string;
    name_ar: string;
    price: string;
};

export type DishOption = {
    id: number;
    name_en: string;
    name_ar: string;
    price: string;
};

export type Dish = {
    id: number;
    name_en: string;
    name_ar: string;
    description_en?: string | null;
    price: string;
    serving_sizes: ServingSize[];
    options: DishOption[];
};

export type Line = {
    quantity: number;
    serving_size_id?: number;
    option_ids: number[];
};

export function lineFor(
    lines: Record<number, Line>,
    dishId: number,
): Line {
    return lines[dishId] ?? { quantity: 0, option_ids: [] };
}

export function unitPriceFor(dish: Dish, line: Line): number {
    const selectedSize = dish.serving_sizes.find(
        (size) => size.id === line.serving_size_id,
    );
    const base = Number(dish.price) + Number(selectedSize?.price ?? 0);
    const extras = line.option_ids.reduce(
        (sum, id) =>
            sum +
            Number(
                dish.options.find((option) => option.id === id)?.price ?? 0,
            ),
        0,
    );

    return Number((base + extras).toFixed(2));
}

function cartStorageKey(restaurantId: number): string {
    return `storefront-cart-${restaurantId}`;
}

/**
 * Read the cart persisted for a restaurant (written by the public
 * storefront page before redirecting to login/checkout), clearing it from
 * storage so it is only ever hydrated once. Lines referencing a dish,
 * serving size, or option that no longer exists in `dishes` are dropped;
 * `removedCount` reports how many lines were dropped so the caller can
 * tell the visitor.
 */
export function readStoredCart(
    restaurantId: number,
    dishes: Dish[],
): { lines: Record<number, Line>; removedCount: number } {
    if (typeof window === 'undefined') {
        return { lines: {}, removedCount: 0 };
    }

    const key = cartStorageKey(restaurantId);
    const raw = window.localStorage.getItem(key);

    if (!raw) {
        return { lines: {}, removedCount: 0 };
    }

    window.localStorage.removeItem(key);

    let parsed: Record<string, Line>;

    try {
        parsed = JSON.parse(raw) as Record<string, Line>;
    } catch {
        return { lines: {}, removedCount: 0 };
    }

    const dishById = new Map(dishes.map((dish) => [dish.id, dish]));
    const lines: Record<number, Line> = {};
    let removedCount = 0;

    for (const [dishIdKey, line] of Object.entries(parsed)) {
        const dishId = Number(dishIdKey);
        const dish = dishById.get(dishId);

        if (!dish || line.quantity <= 0) {
            removedCount += 1;
            continue;
        }

        const servingSizeValid =
            line.serving_size_id === undefined ||
            dish.serving_sizes.some(
                (size) => size.id === line.serving_size_id,
            );
        const optionIds = line.option_ids.filter((id) =>
            dish.options.some((option) => option.id === id),
        );

        if (!servingSizeValid || optionIds.length !== line.option_ids.length) {
            removedCount += 1;
        }

        lines[dishId] = {
            quantity: line.quantity,
            serving_size_id: servingSizeValid
                ? line.serving_size_id
                : undefined,
            option_ids: optionIds,
        };
    }

    return { lines, removedCount };
}

/**
 * Persist the current cart for a restaurant so it survives the
 * login/register redirect from the public storefront page to the
 * authenticated checkout page.
 */
export function writeStoredCart(
    restaurantId: number,
    lines: Record<number, Line>,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    const key = cartStorageKey(restaurantId);
    const nonEmpty = Object.fromEntries(
        Object.entries(lines).filter(([, line]) => line.quantity > 0),
    );

    if (Object.keys(nonEmpty).length === 0) {
        window.localStorage.removeItem(key);
        return;
    }

    window.localStorage.setItem(key, JSON.stringify(nonEmpty));
}
```

- [ ] **Step 2: Type-check and lint**

Run: `npm run types:check`
Expected: no errors.

Run: `npm run lint:check`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/lib/order-cart.ts
git commit -m "feat: add shared order cart library for storefront and checkout"
```

---

### Task 4: Shared dish selector + checkout page cart hydration

**Files:**
- Create: `resources/js/components/dish-selector-card.tsx`
- Modify: `resources/js/pages/restaurants/order.tsx`
- Modify: `resources/js/i18n/locales/en.json`
- Modify: `resources/js/i18n/locales/ar.json`

**Interfaces:**
- Consumes: `Dish`, `Line`, `unitPriceFor` from `resources/js/lib/order-cart.ts` (Task 3); `readStoredCart` (Task 3) for hydration.
- Produces: `DishSelectorCard` component:
  ```typescript
  function DishSelectorCard({
      dish,
      line,
      onChange,
      fieldName,
  }: {
      dish: Dish;
      line: Line;
      onChange: (line: Line) => void;
      fieldName?: (suffix: 'quantity' | 'serving_size_id' | 'option_ids') => string;
  }): JSX.Element
  ```
  `fieldName` is omitted by the public storefront page (Task 5, no native `name` attributes needed) and supplied by `order.tsx` (native form field names, unchanged from today).

- [ ] **Step 1: Extract `DishSelectorCard` from `order.tsx`**

Create `resources/js/components/dish-selector-card.tsx`, moving the per-dish rendering block (name/description/price, quantity input, serving-size select, options checkboxes) out of `resources/js/pages/restaurants/order.tsx:157-343`:

```typescript
import { useTranslation } from 'react-i18next';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { type Dish, type Line, unitPriceFor } from '@/lib/order-cart';

export function DishSelectorCard({
    dish,
    line,
    onChange,
    fieldName,
}: {
    dish: Dish;
    line: Line;
    onChange: (line: Line) => void;
    fieldName?: (
        suffix: 'quantity' | 'serving_size_id' | 'option_ids',
    ) => string;
}) {
    const { t } = useTranslation();

    return (
        <div className="rounded-lg border p-4">
            <div className="flex items-center justify-between gap-4">
                <div>
                    <div className="font-medium">{dish.name_en}</div>
                    {dish.description_en ? (
                        <div className="text-sm text-muted-foreground">
                            {dish.description_en}
                        </div>
                    ) : null}
                    <div className="text-sm">{unitPriceFor(dish, line)}</div>
                </div>

                <div className="w-24">
                    <Label htmlFor={`quantity-${dish.id}`}>
                        {t('orders.quantity')}
                    </Label>
                    <Input
                        id={`quantity-${dish.id}`}
                        name={fieldName?.('quantity')}
                        type="number"
                        min={0}
                        max={99}
                        value={line.quantity}
                        onChange={(e) =>
                            onChange({
                                ...line,
                                quantity: Number(e.target.value),
                            })
                        }
                    />
                </div>
            </div>

            {dish.serving_sizes.length > 0 ? (
                <div className="mt-3">
                    <Label>{t('orders.serving_size')}</Label>
                    <Select
                        name={fieldName?.('serving_size_id')}
                        value={line.serving_size_id?.toString() ?? ''}
                        onValueChange={(value) =>
                            onChange({
                                ...line,
                                serving_size_id: value
                                    ? Number(value)
                                    : undefined,
                            })
                        }
                    >
                        <SelectTrigger className="mt-1 w-full">
                            <SelectValue
                                placeholder={t('orders.serving_size')}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {dish.serving_sizes.map((size) => (
                                <SelectItem
                                    key={size.id}
                                    value={String(size.id)}
                                >
                                    {size.name_en} ({size.price})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            ) : null}

            {dish.options.length > 0 ? (
                <div className="mt-3">
                    <Label>{t('orders.options')}</Label>
                    <div className="mt-2 grid gap-2">
                        {dish.options.map((option) => (
                            <label
                                key={option.id}
                                className="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    name={fieldName?.('option_ids')}
                                    value={String(option.id)}
                                    checked={line.option_ids.includes(
                                        option.id,
                                    )}
                                    onCheckedChange={(checked) =>
                                        onChange({
                                            ...line,
                                            option_ids:
                                                checked === true
                                                    ? [
                                                          ...line.option_ids,
                                                          option.id,
                                                      ]
                                                    : line.option_ids.filter(
                                                          (id) =>
                                                              id !==
                                                              option.id,
                                                      ),
                                        })
                                    }
                                />
                                {option.name_en} ({option.price})
                            </label>
                        ))}
                    </div>
                </div>
            ) : null}

            {line.quantity > 0 ? (
                <p className="mt-2 text-xs text-muted-foreground">
                    {t('orders.in_order')} × {line.quantity}
                </p>
            ) : null}
        </div>
    );
}

export default DishSelectorCard;
```

- [ ] **Step 2: Use `DishSelectorCard` in `order.tsx` and hydrate the cart from `localStorage`**

Rewrite `resources/js/pages/restaurants/order.tsx` in full:

```typescript
import { Form, Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import OrderController from '@/actions/App/Http/Controllers/Orders/OrderController';
import { DishSelectorCard } from '@/components/dish-selector-card';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    type Dish,
    type Line,
    lineFor,
    readStoredCart,
} from '@/lib/order-cart';
import { index as addressesIndex } from '@/routes/addresses';

type Restaurant = { id: number; name_en: string };

type Address = {
    id: number;
    caption: string;
    address: string;
    is_default: boolean;
};

/**
 * Raw shape submitted by the native form fields (see the `lines[dishId][...]`
 * naming below), before `transform` drops zero-quantity entries and reshapes
 * it into the flat `lines` array the server expects.
 */
type RawLine = {
    quantity?: string;
    serving_size_id?: string;
    option_ids?: string[];
};

export default function RestaurantOrder({
    restaurant,
    dishes,
    addresses,
}: {
    restaurant: Restaurant;
    dishes: Dish[];
    addresses: Address[];
}) {
    const { t } = useTranslation();

    const [lines, setLines] = useState<Record<number, Line>>({});
    const [deliveryMode, setDeliveryMode] = useState<'delivery' | 'pickup'>(
        'delivery',
    );
    const [deliveryAddressId, setDeliveryAddressId] = useState<
        string | undefined
    >();

    useEffect(() => {
        const { lines: restoredLines, removedCount } = readStoredCart(
            restaurant.id,
            dishes,
        );

        if (Object.keys(restoredLines).length > 0) {
            setLines(restoredLines);
        }

        if (removedCount > 0) {
            toast.info(t('orders.cart_restored_removed_items'));
        }
    }, [restaurant.id, dishes, t]);

    function setLine(dishId: number, line: Line) {
        setLines((current) => ({ ...current, [dishId]: line }));
    }

    function transform(data: Record<string, unknown>) {
        const rawLines = (data.lines ?? {}) as Record<string, RawLine>;

        return {
            ...data,
            lines: Object.entries(rawLines)
                .filter(([, line]) => Number(line.quantity ?? 0) > 0)
                .map(([dishId, line]) => ({
                    dish_id: Number(dishId),
                    serving_size_id: line.serving_size_id
                        ? Number(line.serving_size_id)
                        : undefined,
                    option_ids: (line.option_ids ?? []).map(Number),
                    quantity: Number(line.quantity),
                })),
            delivery_address_id:
                deliveryMode === 'pickup' ? undefined : deliveryAddressId,
        };
    }

    return (
        <>
            <Head title={t('orders.page_title')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('orders.heading')}
                    description={restaurant.name_en}
                />

                {dishes.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        {t('orders.no_dishes')}
                    </p>
                ) : (
                    <Form
                        {...OrderController.store.form()}
                        transform={transform}
                        className="max-w-2xl space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                {dishes.map((dish) => (
                                    <DishSelectorCard
                                        key={dish.id}
                                        dish={dish}
                                        line={lineFor(lines, dish.id)}
                                        onChange={(line) =>
                                            setLine(dish.id, line)
                                        }
                                        fieldName={(suffix) =>
                                            suffix === 'option_ids'
                                                ? `lines[${dish.id}][option_ids][]`
                                                : `lines[${dish.id}][${suffix}]`
                                        }
                                    />
                                ))}

                                <div className="grid gap-2">
                                    <Label htmlFor="delivery_mode">
                                        {t('orders.delivery_mode.label')}
                                    </Label>
                                    <Select
                                        name="delivery_mode"
                                        value={deliveryMode}
                                        onValueChange={(value) => {
                                            setDeliveryMode(
                                                value as 'delivery' | 'pickup',
                                            );

                                            if (value === 'pickup') {
                                                setDeliveryAddressId(undefined);
                                            }
                                        }}
                                    >
                                        <SelectTrigger
                                            id="delivery_mode"
                                            className="w-full"
                                        >
                                            <SelectValue
                                                placeholder={t(
                                                    'orders.delivery_mode.label',
                                                )}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="delivery">
                                                {t(
                                                    'orders.delivery_mode.delivery',
                                                )}
                                            </SelectItem>
                                            <SelectItem value="pickup">
                                                {t(
                                                    'orders.delivery_mode.pickup',
                                                )}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors['delivery_mode']}
                                    />
                                </div>

                                {deliveryMode === 'delivery' ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="delivery_address_id">
                                            {t('orders.address.label')}
                                        </Label>
                                        <Select
                                            name="delivery_address_id"
                                            value={deliveryAddressId}
                                            onValueChange={setDeliveryAddressId}
                                        >
                                            <SelectTrigger
                                                id="delivery_address_id"
                                                className="w-full"
                                            >
                                                <SelectValue
                                                    placeholder={t(
                                                        'orders.address.label',
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {addresses.map((address) => (
                                                    <SelectItem
                                                        key={address.id}
                                                        value={String(
                                                            address.id,
                                                        )}
                                                    >
                                                        {address.caption} —{' '}
                                                        {address.address}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={
                                                errors['delivery_address_id']
                                            }
                                        />
                                        <Button
                                            asChild
                                            variant="link"
                                            className="px-0"
                                        >
                                            <a href={addressesIndex().url}>
                                                {t('orders.address.empty')}
                                            </a>
                                        </Button>
                                    </div>
                                ) : null}

                                {errors['lines'] ? (
                                    <InputError message={errors['lines']} />
                                ) : null}

                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        Object.values(lines).every(
                                            (line) => line.quantity === 0,
                                        )
                                    }
                                >
                                    {t('orders.submit')}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

RestaurantOrder.layout = {
    breadcrumbs: [
        { title: 'restaurants.index.page_title', href: '/restaurants' },
        { title: 'orders.page_title', href: '' },
    ],
};
```

- [ ] **Step 3: Add the new translation key**

In `resources/js/i18n/locales/en.json`, inside the top-level `"orders"` object (alongside `"no_dishes"`), add:

```json
"cart_restored_removed_items": "Some items were removed from your cart because they're no longer available."
```

In `resources/js/i18n/locales/ar.json`, inside the matching `"orders"` object, add:

```json
"cart_restored_removed_items": "تمت إزالة بعض العناصر من سلتك لأنها لم تعد متاحة."
```

- [ ] **Step 4: Run the existing order tests to confirm no regression**

Run: `php artisan test --compact --filter=OrderCreationTest`
Expected: PASS (props/behavior unchanged — only client-side cart building/hydration changed, which Pest does not execute).

- [ ] **Step 5: Type-check and lint**

Run: `npm run types:check && npm run lint:check`
Expected: no errors.

- [ ] **Step 6: Format and commit**

Run: `vendor/bin/pint --dirty --format agent` (no PHP changed in this task, but run for safety), `npm run format`

```bash
git add resources/js/components/dish-selector-card.tsx resources/js/pages/restaurants/order.tsx resources/js/i18n/locales/en.json resources/js/i18n/locales/ar.json
git commit -m "refactor: extract dish selector card and hydrate checkout cart from storefront"
```

---

### Task 5: Public storefront page

**Files:**
- Create: `resources/js/pages/storefront/show.tsx`
- Modify: `resources/js/app.tsx`
- Modify: `resources/js/i18n/locales/en.json`
- Modify: `resources/js/i18n/locales/ar.json`

**Interfaces:**
- Consumes: `Dish`, `Line`, `lineFor`, `writeStoredCart` from `resources/js/lib/order-cart.ts` (Task 3); `DishSelectorCard` (Task 4); props from `StorefrontController::show` (Task 2); wayfinder-generated `resources/js/routes/storefront/index.ts` and `resources/js/routes/restaurants/index.ts` (`order`).
- Produces: Inertia page `storefront/show`, rendered without the authenticated `AppLayout` shell.

- [ ] **Step 1: Regenerate Wayfinder bindings for the new route**

Run: `php artisan wayfinder:generate`

This produces `resources/js/routes/storefront/index.ts` (exporting `show`) from the `storefront.show` route added in Task 2. These generated files are gitignored (`/resources/js/actions`, `/resources/js/routes` in `.gitignore`) — nothing to commit here, but the files must exist locally for the next step to type-check.

- [ ] **Step 2: Create the storefront page**

`resources/js/pages/storefront/show.tsx`:

```typescript
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppDirectionProvider from '@/components/app-direction-provider';
import { DishSelectorCard } from '@/components/dish-selector-card';
import LanguageSwitcher from '@/components/language-switcher';
import { Button } from '@/components/ui/button';
import { type Dish, type Line, lineFor, writeStoredCart } from '@/lib/order-cart';
import { order } from '@/routes/restaurants';

type Restaurant = {
    id: number;
    name_en: string;
    description_en?: string | null;
};

export default function StorefrontShow({
    restaurant,
    dishes,
}: {
    restaurant: Restaurant;
    dishes: Dish[];
}) {
    const { t } = useTranslation();
    const [lines, setLines] = useState<Record<number, Line>>({});

    function setLine(dishId: number, line: Line) {
        setLines((current) => ({ ...current, [dishId]: line }));
    }

    const hasItems = Object.values(lines).some((line) => line.quantity > 0);

    function handleCheckout() {
        writeStoredCart(restaurant.id, lines);
        router.visit(order.url(restaurant.id));
    }

    return (
        <>
            <Head
                title={t('storefront.page_title', {
                    restaurant: restaurant.name_en,
                })}
            />
            <AppDirectionProvider>
                <div className="mx-auto max-w-2xl space-y-6 p-6">
                    <header className="flex items-center justify-between gap-4">
                        <div>
                            <h1 className="text-xl font-semibold">
                                {restaurant.name_en}
                            </h1>
                            {restaurant.description_en ? (
                                <p className="text-sm text-muted-foreground">
                                    {restaurant.description_en}
                                </p>
                            ) : null}
                        </div>
                        <LanguageSwitcher />
                    </header>

                    {dishes.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            {t('storefront.no_dishes')}
                        </p>
                    ) : (
                        <div className="space-y-4">
                            {dishes.map((dish) => (
                                <DishSelectorCard
                                    key={dish.id}
                                    dish={dish}
                                    line={lineFor(lines, dish.id)}
                                    onChange={(line) => setLine(dish.id, line)}
                                />
                            ))}
                        </div>
                    )}

                    <Button onClick={handleCheckout} disabled={!hasItems}>
                        {t('storefront.checkout')}
                    </Button>
                </div>
            </AppDirectionProvider>
        </>
    );
}
```

- [ ] **Step 3: Render the storefront page without the authenticated app shell**

In `resources/js/app.tsx`, in the `layout` switch inside `createInertiaApp`, add a case before the `default` branch:

```typescript
            case name === 'welcome':
                return null;
            case name.startsWith('storefront/'):
                return null;
            case name.startsWith('auth/'):
```

(`storefront/show` otherwise falls through to `AppLayout`, the authenticated sidebar shell, which would wrongly gate a page meant to render for logged-out visitors.)

- [ ] **Step 4: Add storefront translation keys**

In `resources/js/i18n/locales/en.json`, add a new top-level `"storefront"` object (alphabetically near `"settings"`/`"teams"` or wherever top-level keys are ordered in the file):

```json
"storefront": {
    "page_title": "{{restaurant}} — Menu",
    "no_dishes": "This restaurant has no available dishes yet.",
    "checkout": "Checkout"
}
```

In `resources/js/i18n/locales/ar.json`, add the matching object:

```json
"storefront": {
    "page_title": "{{restaurant}} — القائمة",
    "no_dishes": "لا توجد أطباق متاحة في هذا المطعم بعد.",
    "checkout": "إتمام الطلب"
}
```

- [ ] **Step 5: Run the storefront backend tests to confirm the props contract still matches**

Run: `php artisan test --compact --filter=StorefrontTest`
Expected: PASS.

- [ ] **Step 6: Type-check, lint, and format**

Run: `npm run types:check && npm run lint:check && npm run format`
Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/storefront/show.tsx resources/js/app.tsx resources/js/i18n/locales/en.json resources/js/i18n/locales/ar.json
git commit -m "feat: add public storefront menu page"
```

---

### Task 6: Surface the public link on the restaurant edit page

**Files:**
- Modify: `app/Http/Controllers/Restaurants/RestaurantController.php`
- Modify: `resources/js/pages/restaurants/edit.tsx`
- Modify: `resources/js/i18n/locales/en.json`
- Modify: `resources/js/i18n/locales/ar.json`
- Test: `tests/Feature/Management/RestaurantManagementTest.php`

Without this, nothing in the product surfaces a restaurant's public URL — the spec's "owner shares the link or a QR code on-premise" has no way to find that link. This task closes that loop with the smallest possible addition: show the existing `slug` as a link on the page that already manages the restaurant.

**Interfaces:**
- Consumes: `Restaurant::$slug` (Task 1), route `storefront.show` (Task 2, via wayfinder's `resources/js/routes/storefront/index.ts`).
- Produces: `restaurant.slug` added to the `edit` page's existing Inertia props (no new prop name).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Management/RestaurantManagementTest.php`:

```php
test('the restaurant edit page exposes the public menu slug', function () {
    $restaurant = Restaurant::factory()->create(['name_en' => 'Pizza Palace']);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->get(route('restaurants.edit', $restaurant))
        ->assertInertia(fn ($page) => $page->where('restaurant.slug', 'pizza-palace'));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=RestaurantManagementTest`
Expected: FAIL — `restaurant.slug` prop missing.

- [ ] **Step 3: Add `slug` to the edit page's restaurant prop**

In `app/Http/Controllers/Restaurants/RestaurantController.php`, in `edit()`, change:

```php
            'restaurant' => $restaurant->only(['id', 'company_id', 'name_en', 'name_ar', 'description_en', 'description_ar', 'images']),
```

to:

```php
            'restaurant' => $restaurant->only(['id', 'company_id', 'name_en', 'name_ar', 'description_en', 'description_ar', 'images', 'slug']),
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact --filter=RestaurantManagementTest`
Expected: PASS.

- [ ] **Step 5: Display the link on the edit page**

In `resources/js/pages/restaurants/edit.tsx`:

Add `slug: string;` to the `Restaurant` type (after `description_ar`).

Add this import as the last line of the existing import block (alphabetically after `import { index } from '@/routes/restaurants';`):

```typescript
import { show as storefrontShow } from '@/routes/storefront';
```

In the header block, immediately after the closing `</div>` of the verified-badge `Badge`/verify-`Form` group (still inside the `flex items-center gap-2` wrapper, as a sibling), add:

```tsx
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={storefrontShow.url(restaurant.slug)}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {t('restaurants.edit.public_link')}
                            </a>
                        </Button>
```

- [ ] **Step 6: Add the translation key**

In `resources/js/i18n/locales/en.json`, inside `"restaurants" > "edit"`, add:

```json
"public_link": "View public menu"
```

In `resources/js/i18n/locales/ar.json`, inside the matching `"restaurants" > "edit"`, add:

```json
"public_link": "عرض القائمة العامة"
```

- [ ] **Step 7: Type-check, lint, format, and run PHP tests**

Run: `npm run types:check && npm run lint:check && npm run format`
Run: `vendor/bin/pint --dirty --format agent`
Run: `php artisan test --compact --filter=RestaurantManagementTest`
Expected: all pass.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Restaurants/RestaurantController.php resources/js/pages/restaurants/edit.tsx resources/js/i18n/locales/en.json resources/js/i18n/locales/ar.json tests/Feature/Management/RestaurantManagementTest.php
git commit -m "feat: show the public storefront link on the restaurant edit page"
```

---

### Task 7: Manual browser verification

There is no JS test runner in this project, so the cart hand-off (build cart → redirect to login → land back on checkout with cart restored) can only be exercised by hand. This task is the acceptance check for the whole feature.

**Files:** none (verification only).

- [ ] **Step 1: Build frontend assets**

Run: `npm run build` (or start `npm run dev` if iterating)

- [ ] **Step 2: Seed a verified restaurant with dishes, if one doesn't already exist**

Run: `php artisan tinker --execute '
$restaurant = App\Models\Restaurant::factory()->has(App\Models\RestaurantVerification::factory()->state(["verified" => true]), "verification")->create(["name_en" => "Manual Test Cafe"]);
$category = App\Models\Category::factory()->for($restaurant, "restaurant")->create();
$kitchen = App\Models\Kitchen::factory()->for($restaurant, "restaurant")->create();
$dish = App\Models\Dish::factory()->create(["category_id" => $category->id, "kitchen_id" => $kitchen->id, "name_en" => "Manual Test Dish", "price" => "12.00"]);
echo $restaurant->slug;
'`

- [ ] **Step 3: Walk the flow in the browser (logged out)**

1. Open `https://laravel-app-radix.test/r/<slug-from-step-2>` in a private/incognito window (or log out first).
2. Confirm the menu renders with the seeded dish, no sidebar/app chrome.
3. Set a quantity > 0 on the dish, click "Checkout".
4. Confirm you land on the login page (Laravel's intended-URL redirect).
5. Log in with an existing test user (or register a new one).
6. Confirm you land back on the restaurant's order page (`/restaurants/{id}/order`) with the cart quantity from step 3 already filled in.
7. Submit the order and confirm it succeeds (redirects, success toast).

- [ ] **Step 4: Walk the "removed item" edge case**

1. Repeat steps 1–3 of Step 3 above to get a cart in `localStorage`.
2. Before logging in, mark the dish unavailable: `php artisan tinker --execute 'App\Models\Dish::where("name_en", "Manual Test Dish")->update(["is_available" => false]);'`
3. Log in.
4. Confirm the order page shows an info toast about removed items and the dish/line is not present in the restored cart.

- [ ] **Step 5: Confirm the unverified/unknown-slug 404s**

1. Visit `/r/does-not-exist` — confirm a 404 page.
2. Unverify the seeded restaurant (`php artisan tinker --execute 'App\Models\RestaurantVerification::where("restaurant_id", App\Models\Restaurant::where("name_en", "Manual Test Cafe")->value("id"))->update(["verified" => false]);'`), revisit `/r/<slug>` — confirm 404.

- [ ] **Step 6: Confirm the public link on the restaurant edit page**

1. Log in as an admin or the restaurant's manager, open `/restaurants/{id}/edit`.
2. Confirm the "View public menu" link is present and opens the correct `/r/{slug}` URL in a new tab.

No commit for this task — it's verification of the previous six.
