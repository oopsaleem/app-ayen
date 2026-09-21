# Yemen Oasis Frontend Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the HTTP + Inertia/React management UI for the Company → Restaurant → Kitchen → Menu domain built in the backend plan — CRUD screens for Admins (Companies, Restaurant verification), Managers (Restaurants, Kitchens, Categories, Dishes, DishOptions, ServingSizes), and a read-only kitchen view for Chefs. No customer-facing ordering/menu-browsing UI — that is a later phase.

**Architecture:** Standard Laravel controller → Form Request → Eloquent → `Inertia::render()` per resource, mirroring the existing `App\Http\Controllers\Teams\TeamController` pattern. Routes are **not** nested under the existing `{current_team}` prefix — Company/Restaurant are a standalone tenancy concept (ADR-0001) unrelated to the generic `Team` model. Authorization reuses the `CompanyPolicy`/`RestaurantPolicy` from the backend plan (extended with `create`/`delete` abilities) plus three new policies (`KitchenPolicy`, `CategoryPolicy`, `DishPolicy`); `DishOption`/`ServingSize` mutations authorize against their parent `Dish`. Frontend pages use the Inertia v3 `<Form {...Controller.method.form()}>` pattern (wayfinder-generated actions) already used in `resources/js/pages/settings/profile.tsx`, shadcn/radix UI primitives already in the project, and `useTranslation()` + `i18n/locales/{en,ar}.json` for copy (see `.ai/rules/pagescomponentslayoutshooks.md`).

**Tech Stack:** Laravel 13.25 (PHP 8.4), Inertia.js v3 + React 19, `@laravel/vite-plugin-wayfinder`, i18next, Pest 5.1 (`pestphp/pest-plugin-laravel`), Laravel Pint, ESLint/Prettier/`tsc --noEmit`.

**Spec:** [`CONTEXT.md`](../../../CONTEXT.md), [`docs/adr/0001`–`0006`](../../adr/), [`idea/02-features.md`](../../../idea/02-features.md) (describes the original system's "🟡 Restaurant / company management" CRUD screens this rebuild replaces), and the already-implemented backend plan [`2026-09-21-yemen-oasis-backend-domain.md`](2026-09-21-yemen-oasis-backend-domain.md) (models/policies this plan builds HTTP + UI on top of).

## Global Constraints

- PHP 8.4, Laravel 13.25, Pest 5.1, React 19, Inertia v3 — confirmed via `composer show --direct` / `package.json`; do not use APIs from other major versions.
- No new Composer or npm dependencies — everything in this plan uses packages already installed (shadcn primitives are generated components, not new packages, but no new ones are added here; missing multi-line input needs are met with a plain styled `<textarea>`, not a new `Textarea` package).
- Routes for this domain live outside the `{current_team}` prefix and outside `EnsureTeamMembership` — Company/Restaurant is standalone tenancy (ADR-0001), not the generic Team.
- Bilingual fields (`name_en`/`name_ar`, `description_en`/`description_ar`) always render two inputs side by side, labelled by locale name, never a single "translate" field — never hardcode `'ar'` in frontend code either; use the shared `availableLocales` prop already in `HandleInertiaRequests` to label them.
- Restaurant verification is admin-only and Phase 1 does not gate anything on it — the verify UI is a visible badge + admin-only action button, not an access-control mechanism.
- No uniqueness constraint on Company/Restaurant names (already decided in the backend plan) — do not add client- or server-side uniqueness validation.
- Always use curly braces for control structures, even single-line bodies. Use PHP 8 constructor property promotion where applicable. Explicit return types and param type hints on every method. TitleCase enum case names. PHPDoc blocks (with array-shape types where relevant) over inline comments.
- Run `vendor/bin/pint --dirty --format agent` after writing/editing PHP files in a task, before running tests, and again before committing if it reformats anything.
- Run `npm run types:check` and `npm run lint:check` after writing/editing any `.tsx`/`.ts` file in a task, before committing.
- Run tests with `php artisan test --compact --filter=<TestClassName>` for the task's own test; do not run the full suite every task.
- After adding/changing any route, regenerate wayfinder types with `php artisan wayfinder:generate` (the existing `resources/js/actions` and `resources/js/routes` trees are generated output — do not hand-edit files under those directories).

---

## File Structure

```
app/
  Policies/
    CompanyPolicy.php                   # MODIFY: add create()/delete()
    RestaurantPolicy.php                # MODIFY: add create()/delete()
    KitchenPolicy.php
    CategoryPolicy.php
    DishPolicy.php
  Http/
    Middleware/
      HandleInertiaRequests.php         # MODIFY: share auth.user.roles
    Controllers/
      Companies/
        CompanyController.php
      Restaurants/
        RestaurantController.php
      Kitchens/
        KitchenController.php
      Menu/
        CategoryController.php
        DishController.php
        DishOptionController.php
        ServingSizeController.php
      Chef/
        ChefKitchenController.php
    Requests/
      Companies/
        SaveCompanyRequest.php
      Restaurants/
        SaveRestaurantRequest.php
      Kitchens/
        SaveKitchenRequest.php
      Menu/
        SaveCategoryRequest.php
        SaveDishRequest.php
        SaveDishOptionRequest.php
        SaveServingSizeRequest.php
routes/
  web.php                              # MODIFY: require restaurants.php
  restaurants.php                       # new route file (mirrors routes/settings.php)
resources/js/
  types/
    index.d.ts                         # MODIFY: add roles to Auth, add domain types
  pages/
    companies/
      index.tsx
      create.tsx
      edit.tsx
    restaurants/
      index.tsx
      create.tsx
      edit.tsx
    kitchens/
      create.tsx
      edit.tsx
    menu/
      categories/
        index.tsx
        create.tsx
        edit.tsx
      dishes/
        index.tsx
        create.tsx
        edit.tsx
    chef/
      kitchens.tsx
  components/
    app-sidebar.tsx                     # MODIFY: role-based nav items
  i18n/locales/
    en.json                             # MODIFY: add companies/restaurants/kitchens/menu/chef keys
    ar.json                             # MODIFY: same keys, Arabic copy
tests/
  Feature/
    Authorization/
      KitchenPolicyTest.php
      CategoryPolicyTest.php
      DishPolicyTest.php
    Management/
      CompanyManagementTest.php
      RestaurantManagementTest.php
      RestaurantVerificationTest.php
      KitchenManagementTest.php
      CategoryManagementTest.php
      DishManagementTest.php
      DishOptionManagementTest.php
      ServingSizeManagementTest.php
      ChefKitchenViewTest.php
```

---

### Task 1: Share user roles on Inertia's `auth` prop

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/js/types/index.d.ts`
- Test: `tests/Feature/Identity/SharedAuthRolesTest.php`

**Interfaces:**
- Produces: Inertia shared prop `auth.roles: Array<'admin'|'manager'|'chef'>`, driven by `User::roles()` (already built in the backend plan). Every later frontend task reads this to decide which nav links/pages to show.
- Consumes: `App\Models\User::roles(): array` (backend plan, Task 6).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Admin;
use App\Models\User;

test('the auth shared prop includes the users roles', function () {
    $user = User::factory()->create();
    Admin::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/');

    $response->assertInertia(fn ($page) => $page
        ->where('auth.roles', ['admin']));
});

test('a user with no role rows shares an empty roles array', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertInertia(fn ($page) => $page
        ->where('auth.roles', []));
});
```

Save as `tests/Feature/Identity/SharedAuthRolesTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SharedAuthRolesTest`
Expected: FAIL — `auth.roles` key not present.

- [ ] **Step 3: Update the shared props**

In `app/Http/Middleware/HandleInertiaRequests.php`, replace:

```php
            'auth' => [
                'user' => $user,
            ],
```

with:

```php
            'auth' => [
                'user' => $user,
                'roles' => fn () => $user?->roles() ?? [],
            ],
```

- [ ] **Step 4: Update the shared TypeScript `Auth` type**

Open `resources/js/types/index.d.ts` and find the `Auth` type declaration (`export type Auth = { user: User }` or similar). Replace it with:

```typescript
export type Role = 'admin' | 'manager' | 'chef';

export type Auth = {
    user: User;
    roles: Role[];
};
```

- [ ] **Step 5: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Type-check the frontend**

Run: `npm run types:check`
Expected: no new errors.

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=SharedAuthRolesTest`
Expected: PASS (2 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php resources/js/types/index.d.ts tests/Feature/Identity/SharedAuthRolesTest.php
git commit -m "feat: share user roles on the Inertia auth prop"
```

---

### Task 2: Authorization — create/delete abilities and new policies

**Files:**
- Modify: `app/Policies/CompanyPolicy.php`
- Modify: `app/Policies/RestaurantPolicy.php`
- Create: `app/Policies/KitchenPolicy.php`
- Create: `app/Policies/CategoryPolicy.php`
- Create: `app/Policies/DishPolicy.php`
- Test: `tests/Feature/Authorization/CompanyPolicyTest.php` (MODIFY — add cases)
- Test: `tests/Feature/Authorization/RestaurantPolicyTest.php` (MODIFY — add cases)
- Test: `tests/Feature/Authorization/KitchenPolicyTest.php`
- Test: `tests/Feature/Authorization/CategoryPolicyTest.php`
- Test: `tests/Feature/Authorization/DishPolicyTest.php`

**Interfaces:**
- Produces: `CompanyPolicy::create(User $user): bool`, `CompanyPolicy::delete(User, Company): bool`, `RestaurantPolicy::create(User, Company): bool` (restaurants are created under a company, so `create` takes the parent), `RestaurantPolicy::delete(User, Restaurant): bool`, `KitchenPolicy::{view,create,update,delete}`, `CategoryPolicy::{view,create,update,delete}`, `DishPolicy::{view,create,update,delete}`.
- Consumes: `App\Models\{Company,Restaurant,Kitchen,Category,Dish,User}` (backend plan).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Authorization/CompanyPolicyTest.php`:

```php
test('only admins can create companies', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id]);

    expect($admin->can('create', Company::class))->toBeTrue()
        ->and($manager->can('create', Company::class))->toBeFalse();
});

test('only admins can delete companies', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $company->id]);

    expect($admin->can('delete', $company))->toBeTrue()
        ->and($manager->can('delete', $company))->toBeFalse();
});
```

Add the matching `use` statements (`App\Models\Admin`, `App\Models\Manager`) at the top of that file if not already imported.

Append to `tests/Feature/Authorization/RestaurantPolicyTest.php`:

```php
test('admins and the owning companys manager can create restaurants', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $company->id]);
    $otherManager = User::factory()->create();
    Manager::factory()->create(['user_id' => $otherManager->id]);

    expect($admin->can('create', [Restaurant::class, $company]))->toBeTrue()
        ->and($manager->can('create', [Restaurant::class, $company]))->toBeTrue()
        ->and($otherManager->can('create', [Restaurant::class, $company]))->toBeFalse();
});

test('admins and the owning companys manager can delete restaurants', function () {
    $company = Company::factory()->create();
    $restaurant = Restaurant::factory()->create(['company_id' => $company->id]);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $otherManager = User::factory()->create();
    Manager::factory()->create(['user_id' => $otherManager->id]);

    expect($admin->can('delete', $restaurant))->toBeTrue()
        ->and($otherManager->can('delete', $restaurant))->toBeFalse();
});
```

Create `tests/Feature/Authorization/KitchenPolicyTest.php`:

```php
<?php

use App\Models\Admin;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('admins and the owning companys manager can manage kitchens', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);
    $otherManager = User::factory()->create();
    Manager::factory()->create(['user_id' => $otherManager->id]);

    expect($admin->can('view', $kitchen))->toBeTrue()
        ->and($admin->can('update', $kitchen))->toBeTrue()
        ->and($admin->can('delete', $kitchen))->toBeTrue()
        ->and($manager->can('create', [Kitchen::class, $restaurant]))->toBeTrue()
        ->and($manager->can('update', $kitchen))->toBeTrue()
        ->and($otherManager->can('update', $kitchen))->toBeFalse()
        ->and($otherManager->can('create', [Kitchen::class, $restaurant]))->toBeFalse();
});
```

Create `tests/Feature/Authorization/CategoryPolicyTest.php`:

```php
<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('admins and the owning companys manager can manage categories', function () {
    $restaurant = Restaurant::factory()->create();
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);
    $otherManager = User::factory()->create();
    Manager::factory()->create(['user_id' => $otherManager->id]);

    expect($manager->can('create', [Category::class, $restaurant]))->toBeTrue()
        ->and($manager->can('update', $category))->toBeTrue()
        ->and($manager->can('delete', $category))->toBeTrue()
        ->and($otherManager->can('update', $category))->toBeFalse()
        ->and($admin->can('view', $category))->toBeTrue();
});
```

Create `tests/Feature/Authorization/DishPolicyTest.php`:

```php
<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('admins and the owning companys manager can manage dishes', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $dish = Dish::factory()->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);
    $otherManager = User::factory()->create();
    Manager::factory()->create(['user_id' => $otherManager->id]);

    expect($manager->can('create', [Dish::class, $kitchen]))->toBeTrue()
        ->and($manager->can('update', $dish))->toBeTrue()
        ->and($manager->can('delete', $dish))->toBeTrue()
        ->and($otherManager->can('update', $dish))->toBeFalse()
        ->and($admin->can('view', $dish))->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=CompanyPolicyTest`
Expected: FAIL — `create`/`delete` abilities undefined on `CompanyPolicy`.

Run: `php artisan test --compact --filter=KitchenPolicyTest`
Expected: FAIL — `Class "App\Policies\KitchenPolicy" not found` (no policy registered yet, so `can()` falls through to `false`/exception depending on the gate — the test fails either way).

- [ ] **Step 3: Extend `CompanyPolicy`**

```php
<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * Determine whether the user can view the company.
     */
    public function view(User $user, Company $company): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $company->id;
    }

    /**
     * Determine whether the user can create companies.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the company.
     */
    public function update(User $user, Company $company): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $company->id;
    }

    /**
     * Determine whether the user can delete the company.
     */
    public function delete(User $user, Company $company): bool
    {
        return $user->isAdmin();
    }
}
```

- [ ] **Step 4: Extend `RestaurantPolicy`**

```php
<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\Restaurant;
use App\Models\User;

class RestaurantPolicy
{
    /**
     * Determine whether the user can view the restaurant.
     *
     * Phase 1 does not gate browsing on verification status — an
     * unverified restaurant is just as viewable as a verified one.
     */
    public function view(User $user, Restaurant $restaurant): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create a restaurant under the given company.
     */
    public function create(User $user, Company $company): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $company->id;
    }

    /**
     * Determine whether the user can update the restaurant.
     */
    public function update(User $user, Restaurant $restaurant): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $restaurant->company_id;
    }

    /**
     * Determine whether the user can delete the restaurant.
     */
    public function delete(User $user, Restaurant $restaurant): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $restaurant->company_id;
    }

    /**
     * Determine whether the user can verify the restaurant.
     */
    public function verify(User $user, Restaurant $restaurant): bool
    {
        return $user->isAdmin();
    }
}
```

- [ ] **Step 5: Write `KitchenPolicy`**

```php
<?php

namespace App\Policies;

use App\Models\Kitchen;
use App\Models\Restaurant;
use App\Models\User;

class KitchenPolicy
{
    /**
     * Determine whether the user can view the kitchen.
     */
    public function view(User $user, Kitchen $kitchen): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $kitchen->restaurant->company_id;
    }

    /**
     * Determine whether the user can create a kitchen under the given restaurant.
     */
    public function create(User $user, Restaurant $restaurant): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $restaurant->company_id;
    }

    /**
     * Determine whether the user can update the kitchen.
     */
    public function update(User $user, Kitchen $kitchen): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $kitchen->restaurant->company_id;
    }

    /**
     * Determine whether the user can delete the kitchen.
     */
    public function delete(User $user, Kitchen $kitchen): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $kitchen->restaurant->company_id;
    }
}
```

- [ ] **Step 6: Write `CategoryPolicy`**

```php
<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Restaurant;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view the category.
     */
    public function view(User $user, Category $category): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $category->restaurant->company_id;
    }

    /**
     * Determine whether the user can create a category under the given restaurant.
     */
    public function create(User $user, Restaurant $restaurant): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $restaurant->company_id;
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $category->restaurant->company_id;
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $category->restaurant->company_id;
    }
}
```

- [ ] **Step 7: Write `DishPolicy`**

```php
<?php

namespace App\Policies;

use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\User;

class DishPolicy
{
    /**
     * Determine whether the user can view the dish.
     */
    public function view(User $user, Dish $dish): bool
    {
        return $user->isAdmin()
            || $user->manager?->company_id === $dish->kitchen->restaurant->company_id
            || $user->chef?->kitchens()->whereKey($dish->kitchen_id)->exists();
    }

    /**
     * Determine whether the user can create a dish in the given kitchen.
     */
    public function create(User $user, Kitchen $kitchen): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $kitchen->restaurant->company_id;
    }

    /**
     * Determine whether the user can update the dish.
     */
    public function update(User $user, Dish $dish): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $dish->kitchen->restaurant->company_id;
    }

    /**
     * Determine whether the user can delete the dish.
     */
    public function delete(User $user, Dish $dish): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $dish->kitchen->restaurant->company_id;
    }
}
```

- [ ] **Step 8: Register the new policies**

Laravel 13 auto-discovers policies named `{Model}Policy` in `app/Policies` for models in `app/Models` by convention, matching how `CompanyPolicy`/`RestaurantPolicy` were already picked up in the backend plan with no explicit registration — no `AuthServiceProvider::$policies` array exists in this app. Confirm this by grepping:

Run: `grep -rn "policies" app/Providers/*.php`
Expected: no manual `$policies` map — auto-discovery is in effect, so no registration step is needed for `KitchenPolicy`/`CategoryPolicy`/`DishPolicy`.

- [ ] **Step 9: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 10: Run tests to verify they pass**

Run: `php artisan test --compact --filter=CompanyPolicyTest`
Run: `php artisan test --compact --filter=RestaurantPolicyTest`
Run: `php artisan test --compact --filter=KitchenPolicyTest`
Run: `php artisan test --compact --filter=CategoryPolicyTest`
Run: `php artisan test --compact --filter=DishPolicyTest`
Expected: all PASS.

- [ ] **Step 11: Commit**

```bash
git add app/Policies/CompanyPolicy.php app/Policies/RestaurantPolicy.php app/Policies/KitchenPolicy.php app/Policies/CategoryPolicy.php app/Policies/DishPolicy.php tests/Feature/Authorization/
git commit -m "feat: add create/delete abilities and Kitchen/Category/Dish policies"
```

---

### Task 3: Company management — backend

**Files:**
- Create: `app/Http/Requests/Companies/SaveCompanyRequest.php`
- Create: `app/Http/Controllers/Companies/CompanyController.php`
- Create: `routes/restaurants.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Management/CompanyManagementTest.php`

**Interfaces:**
- Produces: `GET companies` (`companies.index`), `GET companies/create` (`companies.create`), `POST companies` (`companies.store`), `GET companies/{company}/edit` (`companies.edit`), `PATCH companies/{company}` (`companies.update`) — all admin-only.
- Consumes: `App\Models\Company` (backend plan), `App\Policies\CompanyPolicy` (Task 2).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Admin;
use App\Models\Company;
use App\Models\Manager;
use App\Models\User;

test('admins can view the companies index', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    Company::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('companies.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('companies/index')
        ->has('companies', 2));
});

test('non admins cannot view the companies index', function () {
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id]);

    $this->actingAs($manager)->get(route('companies.index'))->assertForbidden();
});

test('admins can create a company', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $response = $this->actingAs($admin)->post(route('companies.store'), [
        'display_name' => 'Yemen Oasis Group',
        'description' => 'A family of Yemeni restaurants.',
    ]);

    $company = Company::firstWhere('display_name', 'Yemen Oasis Group');
    $response->assertRedirect(route('companies.edit', $company));
    $this->assertDatabaseHas('companies', ['display_name' => 'Yemen Oasis Group']);
});

test('company display name is required', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('companies.store'), ['display_name' => ''])
        ->assertInvalid(['display_name']);
});

test('admins can update a company', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $company = Company::factory()->create(['display_name' => 'Old Name']);

    $response = $this->actingAs($admin)->patch(route('companies.update', $company), [
        'display_name' => 'New Name',
        'description' => $company->description,
    ]);

    $response->assertRedirect(route('companies.edit', $company));
    expect($company->fresh()->display_name)->toBe('New Name');
});

test('a manager can view and update their own companys edit page', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $company->id]);

    $this->actingAs($manager)->get(route('companies.edit', $company))
        ->assertInertia(fn ($page) => $page->component('companies/edit'));

    $this->actingAs($manager)->patch(route('companies.update', $company), [
        'display_name' => 'Manager Renamed',
        'description' => null,
    ])->assertRedirect(route('companies.edit', $company));
});
```

Save as `tests/Feature/Management/CompanyManagementTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CompanyManagementTest`
Expected: FAIL — route `companies.index` not defined.

- [ ] **Step 3: Write the form request**

```php
<?php

namespace App\Http\Requests\Companies;

use Illuminate\Foundation\Http\FormRequest;

class SaveCompanyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers\Companies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\SaveCompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /**
     * Display a listing of all companies.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Company::class);

        return Inertia::render('companies/index', [
            'companies' => Company::query()
                ->withCount('restaurants')
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'description']),
        ]);
    }

    /**
     * Show the form for creating a new company.
     */
    public function create(): Response
    {
        Gate::authorize('create', Company::class);

        return Inertia::render('companies/create');
    }

    /**
     * Store a newly created company.
     */
    public function store(SaveCompanyRequest $request): RedirectResponse
    {
        Gate::authorize('create', Company::class);

        $company = Company::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company created.')]);

        return to_route('companies.edit', $company);
    }

    /**
     * Show the form for editing a company.
     */
    public function edit(Company $company): Response
    {
        Gate::authorize('view', $company);

        return Inertia::render('companies/edit', [
            'company' => $company->only(['id', 'display_name', 'description']),
        ]);
    }

    /**
     * Update the specified company.
     */
    public function update(SaveCompanyRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('update', $company);

        $company->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company updated.')]);

        return to_route('companies.edit', $company);
    }
}
```

`Gate::authorize('viewAny', Company::class)` requires a `viewAny` ability; the simplest correct behavior for "admin sees every company" is to reuse `create` semantics (admin-only) rather than invent a fourth ability. Replace the `index()` body's authorization line with:

```php
        abort_unless($request->user()->isAdmin(), 403);
```

and add `use Illuminate\Http\Request;` plus a `Request $request` parameter to `index(Request $request): Response`. Apply that correction before running tests.

- [ ] **Step 5: Write the route file**

```php
<?php

use App\Http\Controllers\Companies\CompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
    Route::patch('companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
});
```

Save as `routes/restaurants.php` (this file grows in later tasks to hold every route in this domain, mirroring `routes/settings.php`).

- [ ] **Step 6: Require the new route file**

In `routes/web.php`, after `require __DIR__.'/settings.php';`, add:

```php
require __DIR__.'/restaurants.php';
```

- [ ] **Step 7: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --compact --filter=CompanyManagementTest`
Expected: PASS (6 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Requests/Companies app/Http/Controllers/Companies routes/restaurants.php routes/web.php tests/Feature/Management/CompanyManagementTest.php
git commit -m "feat: add Company management backend"
```

---

### Task 4: Company management — frontend

**Files:**
- Create: `resources/js/pages/companies/index.tsx`
- Create: `resources/js/pages/companies/create.tsx`
- Create: `resources/js/pages/companies/edit.tsx`
- Modify: `resources/js/i18n/locales/en.json`
- Modify: `resources/js/i18n/locales/ar.json`
- Test: (none new — Task 3's Pest test already asserts `component('companies/index')` / `component('companies/edit')`, which fails to render if the `.tsx` files are missing when the suite runs in CI with SSR/manifest checks; verified here via `npm run build`)

**Interfaces:**
- Consumes: `companies.index`/`create`/`store`/`edit`/`update` routes (Task 3), wayfinder actions generated from `CompanyController`.

- [ ] **Step 1: Regenerate wayfinder types**

Run: `php artisan wayfinder:generate`
Expected: new files under `resources/js/actions/App/Http/Controllers/Companies/CompanyController.ts` and `resources/js/routes/companies/index.ts`.

- [ ] **Step 2: Add i18n keys**

In `resources/js/i18n/locales/en.json`, add a top-level `"companies"` key (alongside the existing `"teams"` key):

```json
"companies": {
    "index": {
        "page_title": "Companies",
        "heading": "Companies",
        "description": "Every company on the platform",
        "new_company": "New company",
        "restaurants_count": "{{count}} restaurant",
        "restaurants_count_plural": "{{count}} restaurants",
        "no_companies": "No companies yet."
    },
    "create": {
        "page_title": "New company",
        "heading": "New company",
        "description": "Add a company to the platform",
        "submit": "Create company"
    },
    "edit": {
        "page_title": "Edit {{name}}",
        "heading": "Company settings",
        "description": "Update this company's details",
        "submit": "Save"
    },
    "fields": {
        "display_name": "Display name",
        "description": "Description"
    }
}
```

In `resources/js/i18n/locales/ar.json`, add the matching Arabic key:

```json
"companies": {
    "index": {
        "page_title": "الشركات",
        "heading": "الشركات",
        "description": "جميع الشركات على المنصة",
        "new_company": "شركة جديدة",
        "restaurants_count": "{{count}} مطعم",
        "restaurants_count_plural": "{{count}} مطاعم",
        "no_companies": "لا توجد شركات بعد."
    },
    "create": {
        "page_title": "شركة جديدة",
        "heading": "شركة جديدة",
        "description": "أضف شركة إلى المنصة",
        "submit": "إنشاء الشركة"
    },
    "edit": {
        "page_title": "تعديل {{name}}",
        "heading": "إعدادات الشركة",
        "description": "تحديث بيانات هذه الشركة",
        "submit": "حفظ"
    },
    "fields": {
        "display_name": "الاسم المعروض",
        "description": "الوصف"
    }
}
```

- [ ] **Step 3: Write `resources/js/pages/companies/index.tsx`**

```tsx
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/companies';

type Company = {
    id: number;
    display_name: string;
    description: string | null;
    restaurants_count: number;
};

export default function CompaniesIndex({ companies }: { companies: Company[] }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('companies.index.page_title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title={t('companies.index.heading')}
                        description={t('companies.index.description')}
                    />

                    <Button asChild>
                        <Link href={create()}>
                            <Plus /> {t('companies.index.new_company')}
                        </Link>
                    </Button>
                </div>

                <div className="space-y-3">
                    {companies.map((company) => (
                        <Link
                            key={company.id}
                            href={edit(company.id)}
                            className="flex items-center justify-between gap-4 rounded-lg border p-4 hover:bg-accent"
                        >
                            <div>
                                <div className="font-medium">{company.display_name}</div>
                                {company.description ? (
                                    <div className="text-sm text-muted-foreground">
                                        {company.description}
                                    </div>
                                ) : null}
                            </div>

                            <span className="text-sm text-muted-foreground">
                                {t('companies.index.restaurants_count', {
                                    count: company.restaurants_count,
                                })}
                            </span>
                        </Link>
                    ))}

                    {companies.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            {t('companies.index.no_companies')}
                        </p>
                    ) : null}
                </div>
            </div>
        </>
    );
}

CompaniesIndex.layout = {
    breadcrumbs: [{ title: 'companies.index.page_title', href: index() }],
};
```

- [ ] **Step 4: Write `resources/js/pages/companies/create.tsx`**

```tsx
import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import CompanyController from '@/actions/App/Http/Controllers/Companies/CompanyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/companies';

export default function CompanyCreate() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('companies.create.page_title')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('companies.create.heading')}
                    description={t('companies.create.description')}
                />

                <Form {...CompanyController.store.form()} className="max-w-xl space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="display_name">
                                    {t('companies.fields.display_name')}
                                </Label>
                                <Input id="display_name" name="display_name" required />
                                <InputError message={errors.display_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">
                                    {t('companies.fields.description')}
                                </Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    rows={3}
                                    className="border-input flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-sm outline-none"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('companies.create.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CompanyCreate.layout = {
    breadcrumbs: [
        { title: 'companies.index.page_title', href: index() },
        { title: 'companies.create.page_title', href: index() },
    ],
};
```

- [ ] **Step 5: Write `resources/js/pages/companies/edit.tsx`**

```tsx
import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import CompanyController from '@/actions/App/Http/Controllers/Companies/CompanyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/companies';

type Company = {
    id: number;
    display_name: string;
    description: string | null;
};

export default function CompanyEdit({ company }: { company: Company }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('companies.edit.page_title', { name: company.display_name })} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('companies.edit.heading')}
                    description={t('companies.edit.description')}
                />

                <Form
                    {...CompanyController.update.form(company.id)}
                    options={{ preserveScroll: true }}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="display_name">
                                    {t('companies.fields.display_name')}
                                </Label>
                                <Input
                                    id="display_name"
                                    name="display_name"
                                    defaultValue={company.display_name}
                                    required
                                />
                                <InputError message={errors.display_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">
                                    {t('companies.fields.description')}
                                </Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    rows={3}
                                    defaultValue={company.description ?? ''}
                                    className="border-input flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-sm outline-none"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('companies.edit.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CompanyEdit.layout = {
    breadcrumbs: [
        { title: 'companies.index.page_title', href: index() },
        { title: 'companies.edit.page_title', href: index() },
    ],
};
```

- [ ] **Step 6: Type-check and lint**

Run: `npm run types:check`
Run: `npm run lint:check`
Expected: no errors.

- [ ] **Step 7: Run the backend test suite for this resource to confirm the pages actually render**

Run: `php artisan test --compact --filter=CompanyManagementTest`
Expected: PASS (6 tests) — Inertia's test assertions resolve the `.tsx` component path via the manifest built by Vite; run `npm run build` once first if this fails with a manifest error.

- [ ] **Step 8: Commit**

```bash
git add resources/js/pages/companies resources/js/i18n/locales/en.json resources/js/i18n/locales/ar.json resources/js/actions/App/Http/Controllers/Companies resources/js/routes/companies
git commit -m "feat: add Company management pages"
```

---

### Task 5: Restaurant management — backend

**Files:**
- Create: `app/Http/Requests/Restaurants/SaveRestaurantRequest.php`
- Create: `app/Http/Controllers/Restaurants/RestaurantController.php`
- Modify: `routes/restaurants.php`
- Test: `tests/Feature/Management/RestaurantManagementTest.php`

**Interfaces:**
- Produces: `GET restaurants` (`restaurants.index`, scoped to the user's company for managers, all for admins), `GET companies/{company}/restaurants/create` (`restaurants.create`), `POST companies/{company}/restaurants` (`restaurants.store`), `GET restaurants/{restaurant}/edit` (`restaurants.edit`), `PATCH restaurants/{restaurant}` (`restaurants.update`). The store/update request accepts a nested `address` array (`address`, `lat`, `lng`) and `upserts` the restaurant's `RestaurantAddress` in the same request.
- Consumes: `App\Models\{Company,Restaurant,RestaurantAddress}` (backend plan), `App\Policies\RestaurantPolicy` (Task 2).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Admin;
use App\Models\Company;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('admins see every restaurant on the index', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    Restaurant::factory()->count(3)->create();

    $this->actingAs($admin)->get(route('restaurants.index'))
        ->assertInertia(fn ($page) => $page->component('restaurants/index')->has('restaurants', 3));
});

test('managers only see their own companys restaurants on the index', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $company->id]);
    Restaurant::factory()->count(2)->create(['company_id' => $company->id]);
    Restaurant::factory()->create();

    $this->actingAs($manager)->get(route('restaurants.index'))
        ->assertInertia(fn ($page) => $page->component('restaurants/index')->has('restaurants', 2));
});

test('a manager can create a restaurant with an address under their company', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $company->id]);

    $response = $this->actingAs($manager)->post(route('restaurants.store', $company), [
        'name_en' => 'Yemen Oasis',
        'name_ar' => 'واحة اليمن',
        'description_en' => null,
        'description_ar' => null,
        'address' => [
            'address' => '123 Main St',
            'lat' => 15.3547,
            'lng' => 44.2066,
        ],
    ]);

    $restaurant = Restaurant::firstWhere('name_en', 'Yemen Oasis');
    $response->assertRedirect(route('restaurants.edit', $restaurant));
    $this->assertDatabaseHas('restaurants', ['name_en' => 'Yemen Oasis', 'company_id' => $company->id]);
    $this->assertDatabaseHas('restaurant_addresses', ['restaurant_id' => $restaurant->id, 'address' => '123 Main St']);
});

test('a manager from a different company cannot create a restaurant under this company', function () {
    $company = Company::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id]);

    $this->actingAs($manager)->post(route('restaurants.store', $company), [
        'name_en' => 'Yemen Oasis',
        'name_ar' => 'واحة اليمن',
        'address' => ['address' => '123 Main St', 'lat' => 15.3547, 'lng' => 44.2066],
    ])->assertForbidden();
});

test('updating a restaurant also updates its address', function () {
    $restaurant = Restaurant::factory()
        ->has(\App\Models\RestaurantAddress::factory(), 'address')
        ->create();
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->patch(route('restaurants.update', $restaurant), [
        'name_en' => $restaurant->name_en,
        'name_ar' => $restaurant->name_ar,
        'address' => ['address' => 'New address', 'lat' => 1.0, 'lng' => 2.0],
    ])->assertRedirect(route('restaurants.edit', $restaurant));

    $this->assertDatabaseHas('restaurant_addresses', ['restaurant_id' => $restaurant->id, 'address' => 'New address']);
});
```

Save as `tests/Feature/Management/RestaurantManagementTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=RestaurantManagementTest`
Expected: FAIL — route `restaurants.index` not defined.

- [ ] **Step 3: Write the form request**

```php
<?php

namespace App\Http\Requests\Restaurants;

use Illuminate\Foundation\Http\FormRequest;

class SaveRestaurantRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'address' => ['required', 'array'],
            'address.address' => ['required', 'string'],
            'address.lat' => ['required', 'numeric', 'between:-90,90'],
            'address.lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers\Restaurants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurants\SaveRestaurantRequest;
use App\Models\Company;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantController extends Controller
{
    /**
     * Display a listing of restaurants visible to the current user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $restaurants = Restaurant::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('company_id', $user->manager?->company_id))
            ->with('address')
            ->orderBy('name_en')
            ->get(['id', 'company_id', 'name_en', 'name_ar']);

        return Inertia::render('restaurants/index', [
            'restaurants' => $restaurants,
        ]);
    }

    /**
     * Show the form for creating a restaurant under the given company.
     */
    public function create(Company $company): Response
    {
        Gate::authorize('create', [Restaurant::class, $company]);

        return Inertia::render('restaurants/create', [
            'company' => $company->only(['id', 'display_name']),
        ]);
    }

    /**
     * Store a newly created restaurant.
     */
    public function store(SaveRestaurantRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', [Restaurant::class, $company]);

        $restaurant = DB::transaction(function () use ($request, $company) {
            $restaurant = Restaurant::create([
                'company_id' => $company->id,
                ...$request->safe()->except('address'),
            ]);

            $restaurant->address()->create($request->validated('address'));

            return $restaurant;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Restaurant created.')]);

        return to_route('restaurants.edit', $restaurant);
    }

    /**
     * Show the form for editing a restaurant.
     */
    public function edit(Restaurant $restaurant): Response
    {
        Gate::authorize('view', $restaurant);

        $restaurant->load('address', 'verification');

        return Inertia::render('restaurants/edit', [
            'restaurant' => $restaurant->only(['id', 'company_id', 'name_en', 'name_ar', 'description_en', 'description_ar', 'images']),
            'address' => $restaurant->address?->only(['address', 'lat', 'lng']),
            'verified' => (bool) $restaurant->verification?->verified,
            'canVerify' => Gate::allows('verify', $restaurant),
        ]);
    }

    /**
     * Update the specified restaurant and its address.
     */
    public function update(SaveRestaurantRequest $request, Restaurant $restaurant): RedirectResponse
    {
        Gate::authorize('update', $restaurant);

        DB::transaction(function () use ($request, $restaurant) {
            $restaurant->update($request->safe()->except('address'));

            $restaurant->address()->updateOrCreate([], $request->validated('address'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Restaurant updated.')]);

        return to_route('restaurants.edit', $restaurant);
    }
}
```

- [ ] **Step 5: Add the routes**

In `routes/restaurants.php`, add imports for `App\Http\Controllers\Restaurants\RestaurantController` and, inside the existing `auth`/`verified` group, after the companies routes:

```php
    Route::get('companies/{company}/restaurants/create', [RestaurantController::class, 'create'])->name('restaurants.create');
    Route::post('companies/{company}/restaurants', [RestaurantController::class, 'store'])->name('restaurants.store');
    Route::get('restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
    Route::get('restaurants/{restaurant}/edit', [RestaurantController::class, 'edit'])->name('restaurants.edit');
    Route::patch('restaurants/{restaurant}', [RestaurantController::class, 'update'])->name('restaurants.update');
```

- [ ] **Step 6: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=RestaurantManagementTest`
Expected: PASS (5 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Restaurants app/Http/Controllers/Restaurants routes/restaurants.php tests/Feature/Management/RestaurantManagementTest.php
git commit -m "feat: add Restaurant management backend"
```

---

### Task 6: Restaurant management — frontend

**Files:**
- Create: `resources/js/pages/restaurants/index.tsx`
- Create: `resources/js/pages/restaurants/create.tsx`
- Create: `resources/js/pages/restaurants/edit.tsx`
- Modify: `resources/js/i18n/locales/en.json`
- Modify: `resources/js/i18n/locales/ar.json`

**Interfaces:**
- Consumes: `restaurants.index`/`create`/`store`/`edit`/`update` routes (Task 5).

- [ ] **Step 1: Regenerate wayfinder types**

Run: `php artisan wayfinder:generate`

- [ ] **Step 2: Add i18n keys**

In `resources/js/i18n/locales/en.json`, add:

```json
"restaurants": {
    "index": {
        "page_title": "Restaurants",
        "heading": "Restaurants",
        "description": "Restaurants you manage",
        "no_restaurants": "No restaurants yet."
    },
    "create": {
        "page_title": "New restaurant",
        "heading": "New restaurant for {{company}}",
        "submit": "Create restaurant"
    },
    "edit": {
        "page_title": "Edit {{name}}",
        "heading": "Restaurant settings",
        "submit": "Save",
        "verified_badge": "Verified",
        "unverified_badge": "Unverified"
    },
    "fields": {
        "name_en": "Name (English)",
        "name_ar": "Name (Arabic)",
        "description_en": "Description (English)",
        "description_ar": "Description (Arabic)",
        "address": "Address",
        "lat": "Latitude",
        "lng": "Longitude"
    }
}
```

In `resources/js/i18n/locales/ar.json`, add:

```json
"restaurants": {
    "index": {
        "page_title": "المطاعم",
        "heading": "المطاعم",
        "description": "المطاعم التي تديرها",
        "no_restaurants": "لا توجد مطاعم بعد."
    },
    "create": {
        "page_title": "مطعم جديد",
        "heading": "مطعم جديد لـ {{company}}",
        "submit": "إنشاء المطعم"
    },
    "edit": {
        "page_title": "تعديل {{name}}",
        "heading": "إعدادات المطعم",
        "submit": "حفظ",
        "verified_badge": "موثّق",
        "unverified_badge": "غير موثّق"
    },
    "fields": {
        "name_en": "الاسم (إنجليزي)",
        "name_ar": "الاسم (عربي)",
        "description_en": "الوصف (إنجليزي)",
        "description_ar": "الوصف (عربي)",
        "address": "العنوان",
        "lat": "خط العرض",
        "lng": "خط الطول"
    }
}
```

- [ ] **Step 3: Write `resources/js/pages/restaurants/index.tsx`**

```tsx
import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { edit, index } from '@/routes/restaurants';

type Restaurant = {
    id: number;
    company_id: number;
    name_en: string;
    name_ar: string;
};

export default function RestaurantsIndex({ restaurants }: { restaurants: Restaurant[] }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('restaurants.index.page_title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('restaurants.index.heading')}
                    description={t('restaurants.index.description')}
                />

                <div className="space-y-3">
                    {restaurants.map((restaurant) => (
                        <Link
                            key={restaurant.id}
                            href={edit(restaurant.id)}
                            className="flex items-center justify-between gap-4 rounded-lg border p-4 hover:bg-accent"
                        >
                            <span className="font-medium">{restaurant.name_en}</span>
                            <span dir="rtl" className="text-sm text-muted-foreground">
                                {restaurant.name_ar}
                            </span>
                        </Link>
                    ))}

                    {restaurants.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            {t('restaurants.index.no_restaurants')}
                        </p>
                    ) : null}
                </div>
            </div>
        </>
    );
}

RestaurantsIndex.layout = {
    breadcrumbs: [{ title: 'restaurants.index.page_title', href: index() }],
};
```

- [ ] **Step 4: Write `resources/js/pages/restaurants/create.tsx`**

```tsx
import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import RestaurantController from '@/actions/App/Http/Controllers/Restaurants/RestaurantController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/restaurants';

type Company = { id: number; display_name: string };

export default function RestaurantCreate({ company }: { company: Company }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('restaurants.create.page_title')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('restaurants.create.heading', { company: company.display_name })}
                />

                <Form
                    {...RestaurantController.store.form(company.id)}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">{t('restaurants.fields.name_en')}</Label>
                                <Input id="name_en" name="name_en" required />
                                <InputError message={errors.name_en} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_ar">{t('restaurants.fields.name_ar')}</Label>
                                <Input id="name_ar" name="name_ar" dir="rtl" required />
                                <InputError message={errors.name_ar} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address_address">
                                    {t('restaurants.fields.address')}
                                </Label>
                                <Input id="address_address" name="address[address]" required />
                                <InputError message={errors['address.address']} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="address_lat">{t('restaurants.fields.lat')}</Label>
                                    <Input
                                        id="address_lat"
                                        name="address[lat]"
                                        type="number"
                                        step="any"
                                        required
                                    />
                                    <InputError message={errors['address.lat']} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="address_lng">{t('restaurants.fields.lng')}</Label>
                                    <Input
                                        id="address_lng"
                                        name="address[lng]"
                                        type="number"
                                        step="any"
                                        required
                                    />
                                    <InputError message={errors['address.lng']} />
                                </div>
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('restaurants.create.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

RestaurantCreate.layout = {
    breadcrumbs: [{ title: 'restaurants.index.page_title', href: index() }],
};
```

- [ ] **Step 5: Write `resources/js/pages/restaurants/edit.tsx`**

```tsx
import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import RestaurantController from '@/actions/App/Http/Controllers/Restaurants/RestaurantController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/restaurants';

type Restaurant = {
    id: number;
    name_en: string;
    name_ar: string;
    description_en: string | null;
    description_ar: string | null;
};

type Address = { address: string; lat: number; lng: number } | undefined;

export default function RestaurantEdit({
    restaurant,
    address,
    verified,
}: {
    restaurant: Restaurant;
    address: Address;
    verified: boolean;
    canVerify: boolean;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('restaurants.edit.page_title', { name: restaurant.name_en })} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading variant="small" title={t('restaurants.edit.heading')} />
                    <Badge variant={verified ? 'default' : 'secondary'}>
                        {t(
                            verified
                                ? 'restaurants.edit.verified_badge'
                                : 'restaurants.edit.unverified_badge',
                        )}
                    </Badge>
                </div>

                <Form
                    {...RestaurantController.update.form(restaurant.id)}
                    options={{ preserveScroll: true }}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">{t('restaurants.fields.name_en')}</Label>
                                <Input id="name_en" name="name_en" defaultValue={restaurant.name_en} required />
                                <InputError message={errors.name_en} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_ar">{t('restaurants.fields.name_ar')}</Label>
                                <Input
                                    id="name_ar"
                                    name="name_ar"
                                    dir="rtl"
                                    defaultValue={restaurant.name_ar}
                                    required
                                />
                                <InputError message={errors.name_ar} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address_address">
                                    {t('restaurants.fields.address')}
                                </Label>
                                <Input
                                    id="address_address"
                                    name="address[address]"
                                    defaultValue={address?.address}
                                    required
                                />
                                <InputError message={errors['address.address']} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="address_lat">{t('restaurants.fields.lat')}</Label>
                                    <Input
                                        id="address_lat"
                                        name="address[lat]"
                                        type="number"
                                        step="any"
                                        defaultValue={address?.lat}
                                        required
                                    />
                                    <InputError message={errors['address.lat']} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="address_lng">{t('restaurants.fields.lng')}</Label>
                                    <Input
                                        id="address_lng"
                                        name="address[lng]"
                                        type="number"
                                        step="any"
                                        defaultValue={address?.lng}
                                        required
                                    />
                                    <InputError message={errors['address.lng']} />
                                </div>
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('restaurants.edit.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

RestaurantEdit.layout = {
    breadcrumbs: [{ title: 'restaurants.index.page_title', href: index() }],
};
```

Note: the verify button is added in Task 7, which extends this same file.

- [ ] **Step 6: Type-check and lint**

Run: `npm run types:check`
Run: `npm run lint:check`

- [ ] **Step 7: Run the backend test to confirm rendering**

Run: `php artisan test --compact --filter=RestaurantManagementTest`
Expected: PASS (5 tests)

- [ ] **Step 8: Commit**

```bash
git add resources/js/pages/restaurants resources/js/i18n/locales/en.json resources/js/i18n/locales/ar.json resources/js/actions/App/Http/Controllers/Restaurants resources/js/routes/restaurants
git commit -m "feat: add Restaurant management pages"
```

---

### Task 7: Restaurant verification — backend + frontend

**Files:**
- Create: `app/Http/Controllers/Restaurants/RestaurantVerificationController.php`
- Modify: `routes/restaurants.php`
- Modify: `resources/js/pages/restaurants/edit.tsx`
- Test: `tests/Feature/Management/RestaurantVerificationTest.php`

**Interfaces:**
- Produces: `POST restaurants/{restaurant}/verify` (`restaurants.verify`) — admin-only, toggles `RestaurantVerification.verified` and stamps `verified_by_admin_id`.
- Consumes: `App\Models\{Restaurant,RestaurantVerification,Admin}`, `RestaurantPolicy::verify` (Task 2), `restaurants/edit.tsx` (Task 6).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Admin;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('an admin can verify a restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $response = $this->actingAs($admin)->post(route('restaurants.verify', $restaurant));

    $response->assertRedirect(route('restaurants.edit', $restaurant));
    $this->assertDatabaseHas('restaurant_verifications', [
        'restaurant_id' => $restaurant->id,
        'verified' => true,
    ]);
});

test('an admin can unverify a previously verified restaurant', function () {
    $restaurant = Restaurant::factory()
        ->has(\App\Models\RestaurantVerification::factory()->state(['verified' => true]), 'verification')
        ->create();
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->post(route('restaurants.verify', $restaurant));

    expect($restaurant->verification()->first()->verified)->toBeFalse();
});

test('a manager cannot verify a restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->post(route('restaurants.verify', $restaurant))->assertForbidden();
});
```

Save as `tests/Feature/Management/RestaurantVerificationTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=RestaurantVerificationTest`
Expected: FAIL — route `restaurants.verify` not defined.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\Restaurants;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RestaurantVerificationController extends Controller
{
    /**
     * Toggle the restaurant's verification status.
     */
    public function __invoke(Request $request, Restaurant $restaurant): RedirectResponse
    {
        Gate::authorize('verify', $restaurant);

        $verification = $restaurant->verification()->firstOrNew();
        $verification->verified = ! $verification->verified;
        $verification->verified_by_admin_id = $verification->verified ? $request->user()->admin->id : null;
        $verification->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Verification updated.')]);

        return to_route('restaurants.edit', $restaurant);
    }
}
```

Add `use Inertia\Inertia;` to the imports.

- [ ] **Step 4: Add the route**

In `routes/restaurants.php`, import `App\Http\Controllers\Restaurants\RestaurantVerificationController` and add, after `restaurants.update`:

```php
    Route::post('restaurants/{restaurant}/verify', RestaurantVerificationController::class)->name('restaurants.verify');
```

- [ ] **Step 5: Add the verify button to `restaurants/edit.tsx`**

In `resources/js/pages/restaurants/edit.tsx`, add the import:

```tsx
import RestaurantVerificationController from '@/actions/App/Http/Controllers/Restaurants/RestaurantVerificationController';
```

Replace the `<Badge>` block with a badge-plus-button when `canVerify` is true:

```tsx
                    <div className="flex items-center gap-2">
                        <Badge variant={verified ? 'default' : 'secondary'}>
                            {t(
                                verified
                                    ? 'restaurants.edit.verified_badge'
                                    : 'restaurants.edit.unverified_badge',
                            )}
                        </Badge>

                        {canVerify ? (
                            <Form {...RestaurantVerificationController.form(restaurant.id)}>
                                {({ processing }) => (
                                    <Button type="submit" variant="outline" size="sm" disabled={processing}>
                                        {t(
                                            verified
                                                ? 'restaurants.edit.unverify'
                                                : 'restaurants.edit.verify',
                                        )}
                                    </Button>
                                )}
                            </Form>
                        ) : null}
                    </div>
```

- [ ] **Step 6: Add the two new i18n keys**

In `resources/js/i18n/locales/en.json`, inside `restaurants.edit`, add:

```json
"verify": "Verify",
"unverify": "Unverify"
```

In `resources/js/i18n/locales/ar.json`, inside `restaurants.edit`, add:

```json
"verify": "توثيق",
"unverify": "إلغاء التوثيق"
```

- [ ] **Step 7: Format, regenerate wayfinder, type-check**

Run: `vendor/bin/pint --dirty --format agent`
Run: `php artisan wayfinder:generate`
Run: `npm run types:check`
Run: `npm run lint:check`

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --compact --filter=RestaurantVerificationTest`
Expected: PASS (3 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Restaurants/RestaurantVerificationController.php routes/restaurants.php resources/js/pages/restaurants/edit.tsx resources/js/i18n/locales/en.json resources/js/i18n/locales/ar.json resources/js/actions/App/Http/Controllers/Restaurants tests/Feature/Management/RestaurantVerificationTest.php
git commit -m "feat: add restaurant verification toggle"
```

---

### Task 8: Kitchen management — backend

**Files:**
- Create: `app/Http/Requests/Kitchens/SaveKitchenRequest.php`
- Create: `app/Http/Controllers/Kitchens/KitchenController.php`
- Modify: `routes/restaurants.php`
- Test: `tests/Feature/Management/KitchenManagementTest.php`

**Interfaces:**
- Produces: `GET restaurants/{restaurant}/kitchens/create` (`kitchens.create`), `POST restaurants/{restaurant}/kitchens` (`kitchens.store`), `GET kitchens/{kitchen}/edit` (`kitchens.edit`), `PATCH kitchens/{kitchen}` (`kitchens.update`). Kitchens list is folded into the restaurant edit page's props (Task 9), not a separate index route.
- Consumes: `App\Models\{Restaurant,Kitchen}`, `App\Policies\KitchenPolicy` (Task 2).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Admin;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('a manager can create a kitchen under their restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $response = $this->actingAs($manager)->post(route('kitchens.store', $restaurant), [
        'name_en' => 'Grill',
        'name_ar' => 'شواية',
    ]);

    $kitchen = Kitchen::firstWhere('name_en', 'Grill');
    $response->assertRedirect(route('restaurants.edit', $restaurant));
    $this->assertDatabaseHas('kitchens', ['restaurant_id' => $restaurant->id, 'name_en' => 'Grill']);
});

test('a manager from a different company cannot create a kitchen here', function () {
    $restaurant = Restaurant::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id]);

    $this->actingAs($manager)->post(route('kitchens.store', $restaurant), [
        'name_en' => 'Grill',
        'name_ar' => 'شواية',
    ])->assertForbidden();
});

test('an admin can edit and update a kitchen', function () {
    $kitchen = Kitchen::factory()->create(['name_en' => 'Old']);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->get(route('kitchens.edit', $kitchen))
        ->assertInertia(fn ($page) => $page->component('kitchens/edit'));

    $this->actingAs($admin)->patch(route('kitchens.update', $kitchen), [
        'name_en' => 'New',
        'name_ar' => $kitchen->name_ar,
    ])->assertRedirect(route('restaurants.edit', $kitchen->restaurant));

    expect($kitchen->fresh()->name_en)->toBe('New');
});
```

Save as `tests/Feature/Management/KitchenManagementTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=KitchenManagementTest`
Expected: FAIL — route `kitchens.store` not defined.

- [ ] **Step 3: Write the form request**

```php
<?php

namespace App\Http\Requests\Kitchens;

use Illuminate\Foundation\Http\FormRequest;

class SaveKitchenRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers\Kitchens;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchens\SaveKitchenRequest;
use App\Models\Kitchen;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class KitchenController extends Controller
{
    /**
     * Store a newly created kitchen under the given restaurant.
     */
    public function store(SaveKitchenRequest $request, Restaurant $restaurant): RedirectResponse
    {
        Gate::authorize('create', [Kitchen::class, $restaurant]);

        $restaurant->kitchens()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kitchen created.')]);

        return to_route('restaurants.edit', $restaurant);
    }

    /**
     * Show the form for editing a kitchen.
     */
    public function edit(Kitchen $kitchen): Response
    {
        Gate::authorize('view', $kitchen);

        return Inertia::render('kitchens/edit', [
            'kitchen' => $kitchen->only(['id', 'restaurant_id', 'name_en', 'name_ar']),
        ]);
    }

    /**
     * Update the specified kitchen.
     */
    public function update(SaveKitchenRequest $request, Kitchen $kitchen): RedirectResponse
    {
        Gate::authorize('update', $kitchen);

        $kitchen->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kitchen updated.')]);

        return to_route('restaurants.edit', $kitchen->restaurant);
    }
}
```

- [ ] **Step 5: Add the routes**

In `routes/restaurants.php`, import `App\Http\Controllers\Kitchens\KitchenController` and add:

```php
    Route::post('restaurants/{restaurant}/kitchens', [KitchenController::class, 'store'])->name('kitchens.store');
    Route::get('kitchens/{kitchen}/edit', [KitchenController::class, 'edit'])->name('kitchens.edit');
    Route::patch('kitchens/{kitchen}', [KitchenController::class, 'update'])->name('kitchens.update');
```

- [ ] **Step 6: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=KitchenManagementTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Kitchens app/Http/Controllers/Kitchens routes/restaurants.php tests/Feature/Management/KitchenManagementTest.php
git commit -m "feat: add Kitchen management backend"
```

---

### Task 9: Kitchen management — frontend

**Files:**
- Modify: `resources/js/pages/restaurants/edit.tsx` (embed the kitchen list + inline "add kitchen" form)
- Modify: `app/Http/Controllers/Restaurants/RestaurantController.php` (share `kitchens` prop on `edit`)
- Create: `resources/js/pages/kitchens/edit.tsx`
- Modify: `resources/js/i18n/locales/en.json`
- Modify: `resources/js/i18n/locales/ar.json`
- Test: `tests/Feature/Management/KitchenManagementTest.php` (MODIFY — assert the new prop)

**Interfaces:**
- Consumes: `kitchens.store`/`edit`/`update` routes (Task 8).

- [ ] **Step 1: Write the failing test addition**

Append to `tests/Feature/Management/KitchenManagementTest.php`:

```php
test('the restaurant edit page lists its kitchens', function () {
    $restaurant = Restaurant::factory()->create();
    Kitchen::factory()->create(['restaurant_id' => $restaurant->id, 'name_en' => 'Grill']);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->get(route('restaurants.edit', $restaurant))
        ->assertInertia(fn ($page) => $page
            ->component('restaurants/edit')
            ->where('kitchens.0.name_en', 'Grill'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=KitchenManagementTest`
Expected: FAIL — `kitchens` prop missing from `restaurants.edit`.

- [ ] **Step 3: Share `kitchens` from `RestaurantController::edit`**

In `app/Http/Controllers/Restaurants/RestaurantController.php`, change:

```php
        $restaurant->load('address', 'verification');
```

to:

```php
        $restaurant->load('address', 'verification', 'kitchens');
```

and add `'kitchens' => $restaurant->kitchens->map->only(['id', 'name_en', 'name_ar']),` to the `Inertia::render('restaurants/edit', [...])` array.

- [ ] **Step 4: Write `resources/js/pages/kitchens/edit.tsx`**

```tsx
import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import KitchenController from '@/actions/App/Http/Controllers/Kitchens/KitchenController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/restaurants';

type Kitchen = { id: number; restaurant_id: number; name_en: string; name_ar: string };

export default function KitchenEdit({ kitchen }: { kitchen: Kitchen }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('kitchens.edit.page_title', { name: kitchen.name_en })} />

            <div className="space-y-6">
                <Heading variant="small" title={t('kitchens.edit.heading')} />

                <Form
                    {...KitchenController.update.form(kitchen.id)}
                    options={{ preserveScroll: true }}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">{t('kitchens.fields.name_en')}</Label>
                                <Input id="name_en" name="name_en" defaultValue={kitchen.name_en} required />
                                <InputError message={errors.name_en} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_ar">{t('kitchens.fields.name_ar')}</Label>
                                <Input
                                    id="name_ar"
                                    name="name_ar"
                                    dir="rtl"
                                    defaultValue={kitchen.name_ar}
                                    required
                                />
                                <InputError message={errors.name_ar} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('kitchens.edit.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

KitchenEdit.layout = {
    breadcrumbs: [{ title: 'restaurants.index.page_title', href: index() }],
};
```

- [ ] **Step 5: Embed the kitchens list and add-kitchen form in `restaurants/edit.tsx`**

Add imports:

```tsx
import KitchenController from '@/actions/App/Http/Controllers/Kitchens/KitchenController';
import { edit as editKitchen } from '@/routes/kitchens';
```

Add a `Kitchen` type and accept `kitchens` as a prop:

```tsx
type Kitchen = { id: number; name_en: string; name_ar: string };
```

and add `kitchens`: `Kitchen[]` to the component's props destructuring. After the closing `</Form>` of the restaurant details form, add:

```tsx
                <div className="space-y-3 border-t pt-6">
                    <Heading variant="small" title={t('kitchens.index.heading')} />

                    {kitchens.map((kitchen) => (
                        <Link
                            key={kitchen.id}
                            href={editKitchen(kitchen.id)}
                            className="flex items-center justify-between rounded-lg border p-3 hover:bg-accent"
                        >
                            <span>{kitchen.name_en}</span>
                            <span dir="rtl" className="text-sm text-muted-foreground">
                                {kitchen.name_ar}
                            </span>
                        </Link>
                    ))}

                    <Form
                        {...KitchenController.store.form(restaurant.id)}
                        className="flex items-end gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="new_kitchen_name_en">
                                        {t('kitchens.fields.name_en')}
                                    </Label>
                                    <Input id="new_kitchen_name_en" name="name_en" required />
                                    <InputError message={errors.name_en} />
                                </div>

                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="new_kitchen_name_ar">
                                        {t('kitchens.fields.name_ar')}
                                    </Label>
                                    <Input id="new_kitchen_name_ar" name="name_ar" dir="rtl" required />
                                    <InputError message={errors.name_ar} />
                                </div>

                                <Button type="submit" disabled={processing}>
                                    {t('kitchens.index.add')}
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
```

Add `Link` to the existing `@inertiajs/react` import if it isn't already imported in this file.

- [ ] **Step 6: Add i18n keys**

In `resources/js/i18n/locales/en.json`, add:

```json
"kitchens": {
    "index": { "heading": "Kitchens", "add": "Add kitchen" },
    "edit": { "page_title": "Edit {{name}}", "heading": "Kitchen settings", "submit": "Save" },
    "fields": { "name_en": "Name (English)", "name_ar": "Name (Arabic)" }
}
```

In `resources/js/i18n/locales/ar.json`, add:

```json
"kitchens": {
    "index": { "heading": "المطابخ", "add": "إضافة مطبخ" },
    "edit": { "page_title": "تعديل {{name}}", "heading": "إعدادات المطبخ", "submit": "حفظ" },
    "fields": { "name_en": "الاسم (إنجليزي)", "name_ar": "الاسم (عربي)" }
}
```

- [ ] **Step 7: Format, regenerate wayfinder, type-check**

Run: `vendor/bin/pint --dirty --format agent`
Run: `php artisan wayfinder:generate`
Run: `npm run types:check`
Run: `npm run lint:check`

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --compact --filter=KitchenManagementTest`
Expected: PASS (4 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Restaurants/RestaurantController.php resources/js/pages/restaurants/edit.tsx resources/js/pages/kitchens resources/js/i18n/locales/en.json resources/js/i18n/locales/ar.json resources/js/actions/App/Http/Controllers/Kitchens resources/js/routes/kitchens tests/Feature/Management/KitchenManagementTest.php
git commit -m "feat: add Kitchen management pages"
```

---

### Task 10: Category management — backend

**Files:**
- Create: `app/Http/Requests/Menu/SaveCategoryRequest.php`
- Create: `app/Http/Controllers/Menu/CategoryController.php`
- Modify: `routes/restaurants.php`
- Test: `tests/Feature/Management/CategoryManagementTest.php`

**Interfaces:**
- Produces: `GET restaurants/{restaurant}/categories` (`categories.index`), `GET restaurants/{restaurant}/categories/create` (`categories.create`), `POST restaurants/{restaurant}/categories` (`categories.store`), `GET categories/{category}/edit` (`categories.edit`), `PATCH categories/{category}` (`categories.update`). The store/update request accepts a nullable `parent_id`, which must belong to the same restaurant (already enforced by `Category`'s cascade/cycle-guard logic from the backend plan — this task only has to pass `parent_id` through and let the model reject a cross-restaurant parent).
- Consumes: `App\Models\{Restaurant,Category}`, `App\Policies\CategoryPolicy` (Task 2).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('the category index lists a restaurants categories', function () {
    $restaurant = Restaurant::factory()->create();
    Category::factory()->count(2)->create(['restaurant_id' => $restaurant->id]);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->get(route('categories.index', $restaurant))
        ->assertInertia(fn ($page) => $page->component('menu/categories/index')->has('categories', 2));
});

test('a manager can create a top level category', function () {
    $restaurant = Restaurant::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $response = $this->actingAs($manager)->post(route('categories.store', $restaurant), [
        'name_en' => 'Grills',
        'name_ar' => 'مشاوي',
        'parent_id' => null,
    ]);

    $category = Category::firstWhere('name_en', 'Grills');
    $response->assertRedirect(route('categories.index', $restaurant));
    $this->assertDatabaseHas('categories', ['restaurant_id' => $restaurant->id, 'name_en' => 'Grills']);
});

test('a manager can create a child category under a parent in the same restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $parent = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->post(route('categories.store', $restaurant), [
        'name_en' => 'Chicken grills',
        'name_ar' => 'مشاوي دجاج',
        'parent_id' => $parent->id,
    ])->assertRedirect(route('categories.index', $restaurant));

    $child = Category::firstWhere('name_en', 'Chicken grills');
    expect($child->parent_id)->toBe($parent->id);
});

test('a manager cannot create a category under another restaurants parent', function () {
    $restaurant = Restaurant::factory()->create();
    $otherRestaurantParent = Category::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->post(route('categories.store', $restaurant), [
        'name_en' => 'Invalid',
        'name_ar' => 'غير صالح',
        'parent_id' => $otherRestaurantParent->id,
    ])->assertInvalid(['parent_id']);
});
```

Save as `tests/Feature/Management/CategoryManagementTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CategoryManagementTest`
Expected: FAIL — route `categories.index` not defined.

- [ ] **Step 3: Write the form request**

```php
<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $restaurant = $this->route('restaurant') ?? $this->route('category')?->restaurant;

        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('restaurant_id', $restaurant?->id),
            ],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\SaveCategoryRequest;
use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of the restaurant's categories.
     */
    public function index(Restaurant $restaurant): Response
    {
        Gate::authorize('view', $restaurant);

        return Inertia::render('menu/categories/index', [
            'restaurant' => $restaurant->only(['id', 'name_en']),
            'categories' => $restaurant->categories()
                ->orderBy('level')->orderBy('name_en')
                ->get(['id', 'parent_id', 'name_en', 'name_ar', 'level']),
        ]);
    }

    /**
     * Show the form for creating a category under the given restaurant.
     */
    public function create(Restaurant $restaurant): Response
    {
        Gate::authorize('create', [Category::class, $restaurant]);

        return Inertia::render('menu/categories/create', [
            'restaurant' => $restaurant->only(['id', 'name_en']),
            'parents' => $restaurant->categories()->get(['id', 'name_en']),
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(SaveCategoryRequest $request, Restaurant $restaurant): RedirectResponse
    {
        Gate::authorize('create', [Category::class, $restaurant]);

        $restaurant->categories()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('categories.index', $restaurant);
    }

    /**
     * Show the form for editing a category.
     */
    public function edit(Category $category): Response
    {
        Gate::authorize('view', $category);

        return Inertia::render('menu/categories/edit', [
            'category' => $category->only(['id', 'restaurant_id', 'parent_id', 'name_en', 'name_ar']),
            'parents' => $category->restaurant->categories()
                ->whereKeyNot($category->id)
                ->get(['id', 'name_en']),
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('categories.index', $category->restaurant);
    }
}
```

- [ ] **Step 5: Add the routes**

In `routes/restaurants.php`, import `App\Http\Controllers\Menu\CategoryController` and add:

```php
    Route::get('restaurants/{restaurant}/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('restaurants/{restaurant}/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('restaurants/{restaurant}/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
```

- [ ] **Step 6: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=CategoryManagementTest`
Expected: PASS (4 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Menu/SaveCategoryRequest.php app/Http/Controllers/Menu/CategoryController.php routes/restaurants.php tests/Feature/Management/CategoryManagementTest.php
git commit -m "feat: add Category management backend"
```

---

### Task 11: Category management — frontend

**Files:**
- Create: `resources/js/pages/menu/categories/index.tsx`
- Create: `resources/js/pages/menu/categories/create.tsx`
- Create: `resources/js/pages/menu/categories/edit.tsx`
- Modify: `resources/js/i18n/locales/en.json`
- Modify: `resources/js/i18n/locales/ar.json`

**Interfaces:**
- Consumes: `categories.index`/`create`/`store`/`edit`/`update` routes (Task 10).

- [ ] **Step 1: Regenerate wayfinder types**

Run: `php artisan wayfinder:generate`

- [ ] **Step 2: Add i18n keys**

In `resources/js/i18n/locales/en.json`, add a `"categories"` key under a new `"menu"` namespace:

```json
"menu": {
    "categories": {
        "index": {
            "page_title": "Categories",
            "heading": "Categories for {{restaurant}}",
            "new_category": "New category",
            "no_categories": "No categories yet.",
            "top_level": "Top level"
        },
        "create": { "page_title": "New category", "heading": "New category", "submit": "Create category" },
        "edit": { "page_title": "Edit {{name}}", "heading": "Category settings", "submit": "Save" },
        "fields": { "name_en": "Name (English)", "name_ar": "Name (Arabic)", "parent": "Parent category" }
    }
}
```

In `resources/js/i18n/locales/ar.json`, add:

```json
"menu": {
    "categories": {
        "index": {
            "page_title": "الأصناف",
            "heading": "أصناف {{restaurant}}",
            "new_category": "صنف جديد",
            "no_categories": "لا توجد أصناف بعد.",
            "top_level": "المستوى الأعلى"
        },
        "create": { "page_title": "صنف جديد", "heading": "صنف جديد", "submit": "إنشاء الصنف" },
        "edit": { "page_title": "تعديل {{name}}", "heading": "إعدادات الصنف", "submit": "حفظ" },
        "fields": { "name_en": "الاسم (إنجليزي)", "name_ar": "الاسم (عربي)", "parent": "الصنف الأصل" }
    }
}
```

- [ ] **Step 3: Write `resources/js/pages/menu/categories/index.tsx`**

```tsx
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create, edit } from '@/routes/categories';

type Category = {
    id: number;
    parent_id: number | null;
    name_en: string;
    name_ar: string;
    level: number;
};

type Restaurant = { id: number; name_en: string };

export default function CategoriesIndex({
    restaurant,
    categories,
}: {
    restaurant: Restaurant;
    categories: Category[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.categories.index.page_title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title={t('menu.categories.index.heading', { restaurant: restaurant.name_en })}
                    />

                    <Button asChild>
                        <Link href={create(restaurant.id)}>
                            <Plus /> {t('menu.categories.index.new_category')}
                        </Link>
                    </Button>
                </div>

                <div className="space-y-2">
                    {categories.map((category) => (
                        <Link
                            key={category.id}
                            href={edit(category.id)}
                            className="flex items-center justify-between rounded-lg border p-3 hover:bg-accent"
                            style={{ marginInlineStart: `${category.level * 1.5}rem` }}
                        >
                            <span>{category.name_en}</span>
                            <span dir="rtl" className="text-sm text-muted-foreground">
                                {category.name_ar}
                            </span>
                        </Link>
                    ))}

                    {categories.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            {t('menu.categories.index.no_categories')}
                        </p>
                    ) : null}
                </div>
            </div>
        </>
    );
}
```

- [ ] **Step 4: Write `resources/js/pages/menu/categories/create.tsx`**

```tsx
import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import CategoryController from '@/actions/App/Http/Controllers/Menu/CategoryController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Restaurant = { id: number; name_en: string };
type ParentOption = { id: number; name_en: string };

export default function CategoryCreate({
    restaurant,
    parents,
}: {
    restaurant: Restaurant;
    parents: ParentOption[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.categories.create.page_title')} />

            <div className="space-y-6">
                <Heading variant="small" title={t('menu.categories.create.heading')} />

                <Form
                    {...CategoryController.store.form(restaurant.id)}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">{t('menu.categories.fields.name_en')}</Label>
                                <Input id="name_en" name="name_en" required />
                                <InputError message={errors.name_en} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_ar">{t('menu.categories.fields.name_ar')}</Label>
                                <Input id="name_ar" name="name_ar" dir="rtl" required />
                                <InputError message={errors.name_ar} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="parent_id">{t('menu.categories.fields.parent')}</Label>
                                <Select name="parent_id">
                                    <SelectTrigger id="parent_id">
                                        <SelectValue placeholder={t('menu.categories.index.top_level')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {parents.map((parent) => (
                                            <SelectItem key={parent.id} value={String(parent.id)}>
                                                {parent.name_en}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.parent_id} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('menu.categories.create.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
```

- [ ] **Step 5: Write `resources/js/pages/menu/categories/edit.tsx`**

```tsx
import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import CategoryController from '@/actions/App/Http/Controllers/Menu/CategoryController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Category = {
    id: number;
    restaurant_id: number;
    parent_id: number | null;
    name_en: string;
    name_ar: string;
};

type ParentOption = { id: number; name_en: string };

export default function CategoryEdit({
    category,
    parents,
}: {
    category: Category;
    parents: ParentOption[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.categories.edit.page_title', { name: category.name_en })} />

            <div className="space-y-6">
                <Heading variant="small" title={t('menu.categories.edit.heading')} />

                <Form
                    {...CategoryController.update.form(category.id)}
                    options={{ preserveScroll: true }}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">{t('menu.categories.fields.name_en')}</Label>
                                <Input id="name_en" name="name_en" defaultValue={category.name_en} required />
                                <InputError message={errors.name_en} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_ar">{t('menu.categories.fields.name_ar')}</Label>
                                <Input
                                    id="name_ar"
                                    name="name_ar"
                                    dir="rtl"
                                    defaultValue={category.name_ar}
                                    required
                                />
                                <InputError message={errors.name_ar} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="parent_id">{t('menu.categories.fields.parent')}</Label>
                                <Select name="parent_id" defaultValue={category.parent_id ? String(category.parent_id) : undefined}>
                                    <SelectTrigger id="parent_id">
                                        <SelectValue placeholder={t('menu.categories.index.top_level')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {parents.map((parent) => (
                                            <SelectItem key={parent.id} value={String(parent.id)}>
                                                {parent.name_en}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.parent_id} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('menu.categories.edit.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
```

- [ ] **Step 6: Type-check and lint**

Run: `npm run types:check`
Run: `npm run lint:check`

- [ ] **Step 7: Run the backend test to confirm rendering**

Run: `php artisan test --compact --filter=CategoryManagementTest`
Expected: PASS (4 tests)

- [ ] **Step 8: Commit**

```bash
git add resources/js/pages/menu/categories resources/js/i18n/locales/en.json resources/js/i18n/locales/ar.json resources/js/actions/App/Http/Controllers/Menu resources/js/routes/categories
git commit -m "feat: add Category management pages"
```

---

### Task 12: Dish management — backend

**Files:**
- Create: `app/Http/Requests/Menu/SaveDishRequest.php`
- Create: `app/Http/Controllers/Menu/DishController.php`
- Modify: `routes/restaurants.php`
- Test: `tests/Feature/Management/DishManagementTest.php`

**Interfaces:**
- Produces: `GET restaurants/{restaurant}/dishes` (`dishes.index`), `GET restaurants/{restaurant}/dishes/create` (`dishes.create`), `POST restaurants/{restaurant}/dishes` (`dishes.store`), `GET dishes/{dish}/edit` (`dishes.edit`), `PATCH dishes/{dish}` (`dishes.update`).
- Consumes: `App\Models\{Restaurant,Kitchen,Category,Dish}`, `App\Policies\DishPolicy` (Task 2). Assumes `Dish` has `name_en`/`name_ar`/`price`/`kitchen_id`/`category_id`/`is_available` columns per the backend plan's Menu tasks (verify exact column names against `database/migrations/*_create_dishes_table.php` before writing the form request — adjust field names here to match if they differ).

- [ ] **Step 1: Confirm the actual `dishes` schema**

Run: `grep -A20 "Schema::create('dishes'" database/migrations/*_create_dishes_table.php`

Use the exact column list from that output for the `rules()` and `Inertia::render()` payloads below — if columns differ from `name_en`/`name_ar`/`price`/`kitchen_id`/`category_id`/`is_available`, substitute the real names throughout this task and Task 13.

- [ ] **Step 2: Write the failing test**

```php
<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('the dish index lists a restaurants dishes', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    Dish::factory()->count(2)->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->get(route('dishes.index', $restaurant))
        ->assertInertia(fn ($page) => $page->component('menu/dishes/index')->has('dishes', 2));
});

test('a manager can create a dish in their restaurants kitchen', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $response = $this->actingAs($manager)->post(route('dishes.store', $restaurant), [
        'name_en' => 'Mandi',
        'name_ar' => 'مندي',
        'price' => 25.5,
        'kitchen_id' => $kitchen->id,
        'category_id' => $category->id,
    ]);

    $dish = Dish::firstWhere('name_en', 'Mandi');
    $response->assertRedirect(route('dishes.index', $restaurant));
    $this->assertDatabaseHas('dishes', ['name_en' => 'Mandi', 'kitchen_id' => $kitchen->id]);
});

test('a manager cannot assign a dish to a kitchen from another restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $otherKitchen = Kitchen::factory()->create();
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->post(route('dishes.store', $restaurant), [
        'name_en' => 'Invalid',
        'name_ar' => 'غير صالح',
        'price' => 10,
        'kitchen_id' => $otherKitchen->id,
        'category_id' => $category->id,
    ])->assertInvalid(['kitchen_id']);
});
```

Save as `tests/Feature/Management/DishManagementTest.php`.

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact --filter=DishManagementTest`
Expected: FAIL — route `dishes.index` not defined.

- [ ] **Step 4: Write the form request**

```php
<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDishRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $restaurant = $this->route('restaurant') ?? $this->route('dish')?->kitchen->restaurant;

        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'kitchen_id' => [
                'required',
                Rule::exists('kitchens', 'id')->where('restaurant_id', $restaurant?->id),
            ],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where('restaurant_id', $restaurant?->id),
            ],
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

```php
<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\SaveDishRequest;
use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DishController extends Controller
{
    /**
     * Display a listing of the restaurant's dishes.
     */
    public function index(Restaurant $restaurant): Response
    {
        Gate::authorize('view', $restaurant);

        return Inertia::render('menu/dishes/index', [
            'restaurant' => $restaurant->only(['id', 'name_en']),
            'dishes' => Dish::query()
                ->whereHas('kitchen', fn ($query) => $query->where('restaurant_id', $restaurant->id))
                ->with(['kitchen:id,name_en', 'category:id,name_en'])
                ->orderBy('name_en')
                ->get(['id', 'kitchen_id', 'category_id', 'name_en', 'name_ar', 'price']),
        ]);
    }

    /**
     * Show the form for creating a dish under the given restaurant.
     */
    public function create(Restaurant $restaurant): Response
    {
        Gate::authorize('view', $restaurant);

        return Inertia::render('menu/dishes/create', [
            'restaurant' => $restaurant->only(['id', 'name_en']),
            'kitchens' => $restaurant->kitchens()->get(['id', 'name_en']),
            'categories' => $restaurant->categories()->get(['id', 'name_en']),
        ]);
    }

    /**
     * Store a newly created dish.
     */
    public function store(SaveDishRequest $request, Restaurant $restaurant): RedirectResponse
    {
        Gate::authorize('create', [Dish::class, $restaurant->kitchens()->findOrFail($request->validated('kitchen_id'))]);

        Dish::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dish created.')]);

        return to_route('dishes.index', $restaurant);
    }

    /**
     * Show the form for editing a dish.
     */
    public function edit(Dish $dish): Response
    {
        Gate::authorize('view', $dish);

        $restaurant = $dish->kitchen->restaurant;

        return Inertia::render('menu/dishes/edit', [
            'dish' => $dish->only(['id', 'kitchen_id', 'category_id', 'name_en', 'name_ar', 'price']),
            'kitchens' => $restaurant->kitchens()->get(['id', 'name_en']),
            'categories' => $restaurant->categories()->get(['id', 'name_en']),
        ]);
    }

    /**
     * Update the specified dish.
     */
    public function update(SaveDishRequest $request, Dish $dish): RedirectResponse
    {
        Gate::authorize('update', $dish);

        $dish->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dish updated.')]);

        return to_route('dishes.index', $dish->kitchen->restaurant);
    }
}
```

- [ ] **Step 6: Add the routes**

In `routes/restaurants.php`, import `App\Http\Controllers\Menu\DishController` and add:

```php
    Route::get('restaurants/{restaurant}/dishes', [DishController::class, 'index'])->name('dishes.index');
    Route::get('restaurants/{restaurant}/dishes/create', [DishController::class, 'create'])->name('dishes.create');
    Route::post('restaurants/{restaurant}/dishes', [DishController::class, 'store'])->name('dishes.store');
    Route::get('dishes/{dish}/edit', [DishController::class, 'edit'])->name('dishes.edit');
    Route::patch('dishes/{dish}', [DishController::class, 'update'])->name('dishes.update');
```

- [ ] **Step 7: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --compact --filter=DishManagementTest`
Expected: PASS (3 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Requests/Menu/SaveDishRequest.php app/Http/Controllers/Menu/DishController.php routes/restaurants.php tests/Feature/Management/DishManagementTest.php
git commit -m "feat: add Dish management backend"
```

---

### Task 13: Dish management — frontend

**Files:**
- Create: `resources/js/pages/menu/dishes/index.tsx`
- Create: `resources/js/pages/menu/dishes/create.tsx`
- Create: `resources/js/pages/menu/dishes/edit.tsx`
- Modify: `resources/js/i18n/locales/en.json`
- Modify: `resources/js/i18n/locales/ar.json`

**Interfaces:**
- Consumes: `dishes.index`/`create`/`store`/`edit`/`update` routes (Task 12).

- [ ] **Step 1: Regenerate wayfinder types**

Run: `php artisan wayfinder:generate`

- [ ] **Step 2: Add i18n keys**

In `resources/js/i18n/locales/en.json`, inside the `"menu"` key added in Task 11, add a sibling `"dishes"` object:

```json
"dishes": {
    "index": {
        "page_title": "Dishes",
        "heading": "Dishes for {{restaurant}}",
        "new_dish": "New dish",
        "no_dishes": "No dishes yet."
    },
    "create": { "page_title": "New dish", "heading": "New dish", "submit": "Create dish" },
    "edit": { "page_title": "Edit {{name}}", "heading": "Dish settings", "submit": "Save" },
    "fields": {
        "name_en": "Name (English)",
        "name_ar": "Name (Arabic)",
        "price": "Price",
        "kitchen": "Kitchen",
        "category": "Category"
    }
}
```

In `resources/js/i18n/locales/ar.json`, add the matching `"dishes"` object:

```json
"dishes": {
    "index": {
        "page_title": "الأطباق",
        "heading": "أطباق {{restaurant}}",
        "new_dish": "طبق جديد",
        "no_dishes": "لا توجد أطباق بعد."
    },
    "create": { "page_title": "طبق جديد", "heading": "طبق جديد", "submit": "إنشاء الطبق" },
    "edit": { "page_title": "تعديل {{name}}", "heading": "إعدادات الطبق", "submit": "حفظ" },
    "fields": {
        "name_en": "الاسم (إنجليزي)",
        "name_ar": "الاسم (عربي)",
        "price": "السعر",
        "kitchen": "المطبخ",
        "category": "الصنف"
    }
}
```

- [ ] **Step 3: Write `resources/js/pages/menu/dishes/index.tsx`**

```tsx
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create, edit } from '@/routes/dishes';

type Dish = {
    id: number;
    name_en: string;
    name_ar: string;
    price: number;
    kitchen: { name_en: string };
    category: { name_en: string };
};

type Restaurant = { id: number; name_en: string };

export default function DishesIndex({ restaurant, dishes }: { restaurant: Restaurant; dishes: Dish[] }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.dishes.index.page_title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title={t('menu.dishes.index.heading', { restaurant: restaurant.name_en })}
                    />

                    <Button asChild>
                        <Link href={create(restaurant.id)}>
                            <Plus /> {t('menu.dishes.index.new_dish')}
                        </Link>
                    </Button>
                </div>

                <div className="space-y-2">
                    {dishes.map((dish) => (
                        <Link
                            key={dish.id}
                            href={edit(dish.id)}
                            className="flex items-center justify-between rounded-lg border p-3 hover:bg-accent"
                        >
                            <div>
                                <div className="font-medium">{dish.name_en}</div>
                                <div className="text-sm text-muted-foreground">
                                    {dish.kitchen.name_en} · {dish.category.name_en}
                                </div>
                            </div>
                            <span>{dish.price}</span>
                        </Link>
                    ))}

                    {dishes.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            {t('menu.dishes.index.no_dishes')}
                        </p>
                    ) : null}
                </div>
            </div>
        </>
    );
}
```

- [ ] **Step 4: Write `resources/js/pages/menu/dishes/create.tsx`**

```tsx
import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import DishController from '@/actions/App/Http/Controllers/Menu/DishController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Restaurant = { id: number; name_en: string };
type Option = { id: number; name_en: string };

export default function DishCreate({
    restaurant,
    kitchens,
    categories,
}: {
    restaurant: Restaurant;
    kitchens: Option[];
    categories: Option[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.dishes.create.page_title')} />

            <div className="space-y-6">
                <Heading variant="small" title={t('menu.dishes.create.heading')} />

                <Form {...DishController.store.form(restaurant.id)} className="max-w-xl space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">{t('menu.dishes.fields.name_en')}</Label>
                                <Input id="name_en" name="name_en" required />
                                <InputError message={errors.name_en} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_ar">{t('menu.dishes.fields.name_ar')}</Label>
                                <Input id="name_ar" name="name_ar" dir="rtl" required />
                                <InputError message={errors.name_ar} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="price">{t('menu.dishes.fields.price')}</Label>
                                <Input id="price" name="price" type="number" step="0.01" min="0" required />
                                <InputError message={errors.price} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="kitchen_id">{t('menu.dishes.fields.kitchen')}</Label>
                                <Select name="kitchen_id" required>
                                    <SelectTrigger id="kitchen_id">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {kitchens.map((kitchen) => (
                                            <SelectItem key={kitchen.id} value={String(kitchen.id)}>
                                                {kitchen.name_en}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.kitchen_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="category_id">{t('menu.dishes.fields.category')}</Label>
                                <Select name="category_id" required>
                                    <SelectTrigger id="category_id">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((category) => (
                                            <SelectItem key={category.id} value={String(category.id)}>
                                                {category.name_en}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.category_id} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('menu.dishes.create.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
```

- [ ] **Step 5: Write `resources/js/pages/menu/dishes/edit.tsx`**

```tsx
import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import DishController from '@/actions/App/Http/Controllers/Menu/DishController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Dish = {
    id: number;
    kitchen_id: number;
    category_id: number;
    name_en: string;
    name_ar: string;
    price: number;
};

type Option = { id: number; name_en: string };

export default function DishEdit({
    dish,
    kitchens,
    categories,
}: {
    dish: Dish;
    kitchens: Option[];
    categories: Option[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.dishes.edit.page_title', { name: dish.name_en })} />

            <div className="space-y-6">
                <Heading variant="small" title={t('menu.dishes.edit.heading')} />

                <Form
                    {...DishController.update.form(dish.id)}
                    options={{ preserveScroll: true }}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">{t('menu.dishes.fields.name_en')}</Label>
                                <Input id="name_en" name="name_en" defaultValue={dish.name_en} required />
                                <InputError message={errors.name_en} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_ar">{t('menu.dishes.fields.name_ar')}</Label>
                                <Input
                                    id="name_ar"
                                    name="name_ar"
                                    dir="rtl"
                                    defaultValue={dish.name_ar}
                                    required
                                />
                                <InputError message={errors.name_ar} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="price">{t('menu.dishes.fields.price')}</Label>
                                <Input
                                    id="price"
                                    name="price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    defaultValue={dish.price}
                                    required
                                />
                                <InputError message={errors.price} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="kitchen_id">{t('menu.dishes.fields.kitchen')}</Label>
                                <Select name="kitchen_id" defaultValue={String(dish.kitchen_id)} required>
                                    <SelectTrigger id="kitchen_id">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {kitchens.map((kitchen) => (
                                            <SelectItem key={kitchen.id} value={String(kitchen.id)}>
                                                {kitchen.name_en}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.kitchen_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="category_id">{t('menu.dishes.fields.category')}</Label>
                                <Select name="category_id" defaultValue={String(dish.category_id)} required>
                                    <SelectTrigger id="category_id">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((category) => (
                                            <SelectItem key={category.id} value={String(category.id)}>
                                                {category.name_en}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.category_id} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('menu.dishes.edit.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
```

- [ ] **Step 6: Type-check and lint**

Run: `npm run types:check`
Run: `npm run lint:check`

- [ ] **Step 7: Run the backend test to confirm rendering**

Run: `php artisan test --compact --filter=DishManagementTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add resources/js/pages/menu/dishes resources/js/i18n/locales/en.json resources/js/i18n/locales/ar.json resources/js/actions/App/Http/Controllers/Menu resources/js/routes/dishes
git commit -m "feat: add Dish management pages"
```

---

### Task 14: DishOption & ServingSize management — backend

**Files:**
- Create: `app/Http/Requests/Menu/SaveDishOptionRequest.php`
- Create: `app/Http/Requests/Menu/SaveServingSizeRequest.php`
- Create: `app/Http/Controllers/Menu/DishOptionController.php`
- Create: `app/Http/Controllers/Menu/ServingSizeController.php`
- Modify: `routes/restaurants.php`
- Modify: `app/Http/Controllers/Menu/DishController.php` (share options/serving sizes on `edit`)
- Test: `tests/Feature/Management/DishOptionManagementTest.php`
- Test: `tests/Feature/Management/ServingSizeManagementTest.php`

**Interfaces:**
- Produces: `POST dishes/{dish}/options` (`dish-options.store`), `PATCH dish-options/{dishOption}` (`dish-options.update`), `DELETE dish-options/{dishOption}` (`dish-options.destroy`); `POST dishes/{dish}/serving-sizes` (`serving-sizes.store`), `PATCH serving-sizes/{servingSize}` (`serving-sizes.update`), `DELETE serving-sizes/{servingSize}` (`serving-sizes.destroy`). Creating/updating a serving size with `is_default: true` unsets any other default on the same dish (the "exactly one default per dish" rule this domain's backend plan calls out as missing in the original system — do not reproduce that gap).
- Consumes: `App\Models\{Dish,DishOption,ServingSize}` (backend plan). Authorization for both reuses `Gate::authorize('update', $dishOption->dish)` / `Gate::authorize('update', $servingSize->dish)` — no dedicated policy classes, per this plan's YAGNI stance on sub-resource policies.

- [ ] **Step 1: Confirm the actual `dish_options`/`serving_sizes` schema**

Run: `grep -A15 "Schema::create('dish_options'" database/migrations/*_create_dish_options_table.php`
Run: `grep -A15 "Schema::create('serving_sizes'" database/migrations/*_create_serving_sizes_table.php`

Use the real column names (expected: `dish_id`, `name_en`, `name_ar`, `price` for `DishOption`; `dish_id`, `name_en`, `name_ar`, `price`, `is_default` for `ServingSize`) in the requests/controllers below — substitute if they differ.

- [ ] **Step 2: Write the failing tests**

```php
<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Dish;
use App\Models\DishOption;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('a manager can add an option to their dish', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $dish = Dish::factory()->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $response = $this->actingAs($manager)->post(route('dish-options.store', $dish), [
        'name_en' => 'Extra cheese',
        'name_ar' => 'جبنة إضافية',
        'price' => 2.5,
    ]);

    $response->assertRedirect(route('dishes.edit', $dish));
    $this->assertDatabaseHas('dish_options', ['dish_id' => $dish->id, 'name_en' => 'Extra cheese']);
});

test('a manager can update and delete an option on their dish', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $dish = Dish::factory()->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    $option = DishOption::factory()->create(['dish_id' => $dish->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->patch(route('dish-options.update', $option), [
        'name_en' => 'Renamed',
        'name_ar' => $option->name_ar,
        'price' => 3,
    ])->assertRedirect(route('dishes.edit', $dish));

    expect($option->fresh()->name_en)->toBe('Renamed');

    $this->actingAs($manager)->delete(route('dish-options.destroy', $option))
        ->assertRedirect(route('dishes.edit', $dish));

    $this->assertDatabaseMissing('dish_options', ['id' => $option->id]);
});

test('a manager from another company cannot manage an option', function () {
    $dish = Dish::factory()->create();
    $option = DishOption::factory()->create(['dish_id' => $dish->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id]);

    $this->actingAs($manager)->patch(route('dish-options.update', $option), [
        'name_en' => 'Renamed', 'name_ar' => 'x', 'price' => 1,
    ])->assertForbidden();
});
```

Save as `tests/Feature/Management/DishOptionManagementTest.php`.

```php
<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\ServingSize;
use App\Models\User;

test('a manager can add a serving size and mark it default', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $dish = Dish::factory()->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->post(route('serving-sizes.store', $dish), [
        'name_en' => 'Small', 'name_ar' => 'صغير', 'price' => 10, 'is_default' => true,
    ])->assertRedirect(route('dishes.edit', $dish));

    $this->assertDatabaseHas('serving_sizes', ['dish_id' => $dish->id, 'name_en' => 'Small', 'is_default' => true]);
});

test('marking a new serving size as default unsets the previous default', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $dish = Dish::factory()->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    $existingDefault = ServingSize::factory()->create(['dish_id' => $dish->id, 'is_default' => true]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    $this->actingAs($manager)->post(route('serving-sizes.store', $dish), [
        'name_en' => 'Large', 'name_ar' => 'كبير', 'price' => 20, 'is_default' => true,
    ]);

    expect($existingDefault->fresh()->is_default)->toBeFalse();
    $this->assertDatabaseHas('serving_sizes', ['dish_id' => $dish->id, 'name_en' => 'Large', 'is_default' => true]);
});
```

Save as `tests/Feature/Management/ServingSizeManagementTest.php`.

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test --compact --filter=DishOptionManagementTest`
Run: `php artisan test --compact --filter=ServingSizeManagementTest`
Expected: both FAIL — routes not defined.

- [ ] **Step 4: Write the form requests**

```php
<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;

class SaveDishOptionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
```

```php
<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;

class SaveServingSizeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
```

- [ ] **Step 5: Write `DishOptionController`**

```php
<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\SaveDishOptionRequest;
use App\Models\Dish;
use App\Models\DishOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DishOptionController extends Controller
{
    /**
     * Store a newly created option under the given dish.
     */
    public function store(SaveDishOptionRequest $request, Dish $dish): RedirectResponse
    {
        Gate::authorize('update', $dish);

        $dish->options()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option added.')]);

        return to_route('dishes.edit', $dish);
    }

    /**
     * Update the specified option.
     */
    public function update(SaveDishOptionRequest $request, DishOption $dishOption): RedirectResponse
    {
        Gate::authorize('update', $dishOption->dish);

        $dishOption->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option updated.')]);

        return to_route('dishes.edit', $dishOption->dish);
    }

    /**
     * Remove the specified option.
     */
    public function destroy(DishOption $dishOption): RedirectResponse
    {
        Gate::authorize('update', $dishOption->dish);

        $dish = $dishOption->dish;
        $dishOption->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option removed.')]);

        return to_route('dishes.edit', $dish);
    }
}
```

Add a `DishOption::dish(): BelongsTo` relation and `Dish::options(): HasMany` relation to those models now if the backend plan didn't already add them — check first:

Run: `grep -n "function options\|function dish(" app/Models/Dish.php app/Models/DishOption.php`

If either relation is missing, add it following the existing pattern from `app/Models/Restaurant.php`'s `hasMany`/`belongsTo` methods (constructor-promoted nothing needed here, just a typed relation method with a one-line PHPDoc `@return` tag).

- [ ] **Step 6: Write `ServingSizeController`**

```php
<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\SaveServingSizeRequest;
use App\Models\Dish;
use App\Models\ServingSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ServingSizeController extends Controller
{
    /**
     * Store a newly created serving size under the given dish.
     */
    public function store(SaveServingSizeRequest $request, Dish $dish): RedirectResponse
    {
        Gate::authorize('update', $dish);

        DB::transaction(function () use ($request, $dish) {
            if ($request->boolean('is_default')) {
                $dish->servingSizes()->update(['is_default' => false]);
            }

            $dish->servingSizes()->create($request->validated());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Serving size added.')]);

        return to_route('dishes.edit', $dish);
    }

    /**
     * Update the specified serving size.
     */
    public function update(SaveServingSizeRequest $request, ServingSize $servingSize): RedirectResponse
    {
        Gate::authorize('update', $servingSize->dish);

        DB::transaction(function () use ($request, $servingSize) {
            if ($request->boolean('is_default')) {
                $servingSize->dish->servingSizes()->whereKeyNot($servingSize->id)->update(['is_default' => false]);
            }

            $servingSize->update($request->validated());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Serving size updated.')]);

        return to_route('dishes.edit', $servingSize->dish);
    }

    /**
     * Remove the specified serving size.
     */
    public function destroy(ServingSize $servingSize): RedirectResponse
    {
        Gate::authorize('update', $servingSize->dish);

        $dish = $servingSize->dish;
        $servingSize->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Serving size removed.')]);

        return to_route('dishes.edit', $dish);
    }
}
```

Confirm/add `Dish::servingSizes(): HasMany` and `ServingSize::dish(): BelongsTo` the same way as Step 5.

- [ ] **Step 7: Add the routes**

In `routes/restaurants.php`, import `App\Http\Controllers\Menu\{DishOptionController, ServingSizeController}` and add:

```php
    Route::post('dishes/{dish}/options', [DishOptionController::class, 'store'])->name('dish-options.store');
    Route::patch('dish-options/{dishOption}', [DishOptionController::class, 'update'])->name('dish-options.update');
    Route::delete('dish-options/{dishOption}', [DishOptionController::class, 'destroy'])->name('dish-options.destroy');

    Route::post('dishes/{dish}/serving-sizes', [ServingSizeController::class, 'store'])->name('serving-sizes.store');
    Route::patch('serving-sizes/{servingSize}', [ServingSizeController::class, 'update'])->name('serving-sizes.update');
    Route::delete('serving-sizes/{servingSize}', [ServingSizeController::class, 'destroy'])->name('serving-sizes.destroy');
```

- [ ] **Step 8: Share options/serving sizes on `DishController::edit`**

In `app/Http/Controllers/Menu/DishController.php`, in `edit()`, add to the returned array:

```php
            'options' => $dish->options()->get(['id', 'name_en', 'name_ar', 'price']),
            'servingSizes' => $dish->servingSizes()->get(['id', 'name_en', 'name_ar', 'price', 'is_default']),
```

- [ ] **Step 9: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 10: Run tests to verify they pass**

Run: `php artisan test --compact --filter=DishOptionManagementTest`
Run: `php artisan test --compact --filter=ServingSizeManagementTest`
Expected: both PASS (3 tests, 2 tests respectively).

- [ ] **Step 11: Commit**

```bash
git add app/Http/Requests/Menu/SaveDishOptionRequest.php app/Http/Requests/Menu/SaveServingSizeRequest.php app/Http/Controllers/Menu/DishOptionController.php app/Http/Controllers/Menu/ServingSizeController.php app/Http/Controllers/Menu/DishController.php routes/restaurants.php tests/Feature/Management/DishOptionManagementTest.php tests/Feature/Management/ServingSizeManagementTest.php
git commit -m "feat: add DishOption and ServingSize management backend"
```

---

### Task 15: DishOption & ServingSize management — frontend

**Files:**
- Modify: `resources/js/pages/menu/dishes/edit.tsx` (embed options + serving-sizes editors)
- Modify: `resources/js/i18n/locales/en.json`
- Modify: `resources/js/i18n/locales/ar.json`

**Interfaces:**
- Consumes: `dish-options.store`/`update`/`destroy` and `serving-sizes.store`/`update`/`destroy` routes (Task 14).

- [ ] **Step 1: Regenerate wayfinder types**

Run: `php artisan wayfinder:generate`

- [ ] **Step 2: Add i18n keys**

In `resources/js/i18n/locales/en.json`, inside `menu.dishes`, add:

```json
"options": {
    "heading": "Options",
    "add": "Add option",
    "remove": "Remove"
},
"serving_sizes": {
    "heading": "Serving sizes",
    "add": "Add serving size",
    "default": "Default",
    "remove": "Remove"
}
```

In `resources/js/i18n/locales/ar.json`, inside `menu.dishes`, add:

```json
"options": {
    "heading": "الإضافات",
    "add": "إضافة خيار",
    "remove": "إزالة"
},
"serving_sizes": {
    "heading": "أحجام التقديم",
    "add": "إضافة حجم",
    "default": "افتراضي",
    "remove": "إزالة"
}
```

- [ ] **Step 3: Extend `resources/js/pages/menu/dishes/edit.tsx`**

Add imports:

```tsx
import DishOptionController from '@/actions/App/Http/Controllers/Menu/DishOptionController';
import ServingSizeController from '@/actions/App/Http/Controllers/Menu/ServingSizeController';
import { Checkbox } from '@/components/ui/checkbox';
```

Add types and accept the two new props:

```tsx
type DishOption = { id: number; name_en: string; name_ar: string; price: number };
type ServingSize = { id: number; name_en: string; name_ar: string; price: number; is_default: boolean };
```

and add `options: DishOption[]` and `servingSizes: ServingSize[]` to the component's props destructuring. After the closing `</Form>` of the dish details form, add:

```tsx
                <div className="space-y-3 border-t pt-6">
                    <Heading variant="small" title={t('menu.dishes.options.heading')} />

                    {options.map((option) => (
                        <div key={option.id} className="flex items-center gap-2 rounded-lg border p-3">
                            <span className="flex-1">{option.name_en} — {option.price}</span>
                            <Form {...DishOptionController.destroy.form(option.id)}>
                                {({ processing }) => (
                                    <Button type="submit" variant="ghost" size="sm" disabled={processing}>
                                        {t('menu.dishes.options.remove')}
                                    </Button>
                                )}
                            </Form>
                        </div>
                    ))}

                    <Form {...DishOptionController.store.form(dish.id)} className="flex items-end gap-2">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="option_name_en">{t('menu.dishes.fields.name_en')}</Label>
                                    <Input id="option_name_en" name="name_en" required />
                                    <InputError message={errors.name_en} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="option_name_ar">{t('menu.dishes.fields.name_ar')}</Label>
                                    <Input id="option_name_ar" name="name_ar" dir="rtl" required />
                                    <InputError message={errors.name_ar} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="option_price">{t('menu.dishes.fields.price')}</Label>
                                    <Input id="option_price" name="price" type="number" step="0.01" min="0" required />
                                    <InputError message={errors.price} />
                                </div>
                                <Button type="submit" disabled={processing}>
                                    {t('menu.dishes.options.add')}
                                </Button>
                            </>
                        )}
                    </Form>
                </div>

                <div className="space-y-3 border-t pt-6">
                    <Heading variant="small" title={t('menu.dishes.serving_sizes.heading')} />

                    {servingSizes.map((servingSize) => (
                        <div key={servingSize.id} className="flex items-center gap-2 rounded-lg border p-3">
                            <span className="flex-1">
                                {servingSize.name_en} — {servingSize.price}
                                {servingSize.is_default ? ` (${t('menu.dishes.serving_sizes.default')})` : ''}
                            </span>
                            <Form {...ServingSizeController.destroy.form(servingSize.id)}>
                                {({ processing }) => (
                                    <Button type="submit" variant="ghost" size="sm" disabled={processing}>
                                        {t('menu.dishes.serving_sizes.remove')}
                                    </Button>
                                )}
                            </Form>
                        </div>
                    ))}

                    <Form {...ServingSizeController.store.form(dish.id)} className="flex items-end gap-2">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="serving_size_name_en">{t('menu.dishes.fields.name_en')}</Label>
                                    <Input id="serving_size_name_en" name="name_en" required />
                                    <InputError message={errors.name_en} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="serving_size_name_ar">{t('menu.dishes.fields.name_ar')}</Label>
                                    <Input id="serving_size_name_ar" name="name_ar" dir="rtl" required />
                                    <InputError message={errors.name_ar} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="serving_size_price">{t('menu.dishes.fields.price')}</Label>
                                    <Input
                                        id="serving_size_price"
                                        name="price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                    />
                                    <InputError message={errors.price} />
                                </div>
                                <div className="flex items-center gap-2 pb-2">
                                    <Checkbox id="serving_size_is_default" name="is_default" />
                                    <Label htmlFor="serving_size_is_default">
                                        {t('menu.dishes.serving_sizes.default')}
                                    </Label>
                                </div>
                                <Button type="submit" disabled={processing}>
                                    {t('menu.dishes.serving_sizes.add')}
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
```

- [ ] **Step 4: Type-check and lint**

Run: `npm run types:check`
Run: `npm run lint:check`

- [ ] **Step 5: Run the backend tests to confirm rendering**

Run: `php artisan test --compact --filter=DishOptionManagementTest`
Run: `php artisan test --compact --filter=ServingSizeManagementTest`
Expected: both PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/menu/dishes/edit.tsx resources/js/i18n/locales/en.json resources/js/i18n/locales/ar.json resources/js/actions/App/Http/Controllers/Menu resources/js/routes
git commit -m "feat: add DishOption and ServingSize inline editors"
```

---

### Task 16: Chef kitchen view — backend + frontend

**Files:**
- Create: `app/Http/Controllers/Chef/ChefKitchenController.php`
- Modify: `routes/restaurants.php`
- Create: `resources/js/pages/chef/kitchens.tsx`
- Modify: `resources/js/i18n/locales/en.json`
- Modify: `resources/js/i18n/locales/ar.json`
- Test: `tests/Feature/Management/ChefKitchenViewTest.php`

**Interfaces:**
- Produces: `GET chef/kitchens` (`chef.kitchens.index`) — read-only, lists the current chef's assigned kitchens and each kitchen's dishes; 403s for a user with no `Chef` row.
- Consumes: `User::chef` (backend plan), `Chef::kitchens()` (backend plan Task 5), `Kitchen::dishes()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Chef;
use App\Models\ChefKitchen;
use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\User;

test('a chef sees only their assigned kitchens and dishes', function () {
    $chefUser = User::factory()->create();
    $chef = Chef::factory()->create(['user_id' => $chefUser->id]);
    $assignedKitchen = Kitchen::factory()->create();
    $otherKitchen = Kitchen::factory()->create();

    ChefKitchen::create(['chef_id' => $chef->id, 'kitchen_id' => $assignedKitchen->id, 'assigned_at' => now()]);
    Dish::factory()->create(['kitchen_id' => $assignedKitchen->id]);
    Dish::factory()->create(['kitchen_id' => $otherKitchen->id]);

    $response = $this->actingAs($chefUser)->get(route('chef.kitchens.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('chef/kitchens')
        ->has('kitchens', 1)
        ->has('kitchens.0.dishes', 1));
});

test('a non chef cannot view the chef kitchen page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('chef.kitchens.index'))->assertForbidden();
});
```

Save as `tests/Feature/Management/ChefKitchenViewTest.php`.

Check the exact pivot column names for `chef_kitchen` before writing this test:

Run: `grep -A10 "Schema::create('chef_kitchen'" database/migrations/*_create_chef_kitchen_table.php`

Adjust the `ChefKitchen::create([...])` call above to match if the columns differ from `chef_id`/`kitchen_id`/`assigned_at`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ChefKitchenViewTest`
Expected: FAIL — route `chef.kitchens.index` not defined.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\Chef;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChefKitchenController extends Controller
{
    /**
     * Display the chef's assigned kitchens and their dishes.
     */
    public function index(Request $request): Response
    {
        $chef = $request->user()->chef;

        abort_unless($chef !== null, 403);

        return Inertia::render('chef/kitchens', [
            'kitchens' => $chef->kitchens()
                ->with(['dishes' => fn ($query) => $query->select('id', 'kitchen_id', 'name_en', 'name_ar', 'price')])
                ->get(['kitchens.id', 'kitchens.name_en', 'kitchens.name_ar']),
        ]);
    }
}
```

Add `Kitchen::dishes(): HasMany` to `app/Models/Kitchen.php` if it isn't already there — check first:

Run: `grep -n "function dishes" app/Models/Kitchen.php`

If missing, add it following the same pattern as `Restaurant::kitchens()`.

- [ ] **Step 4: Add the route**

In `routes/restaurants.php`, import `App\Http\Controllers\Chef\ChefKitchenController` and add:

```php
    Route::get('chef/kitchens', [ChefKitchenController::class, 'index'])->name('chef.kitchens.index');
```

- [ ] **Step 5: Add i18n keys**

In `resources/js/i18n/locales/en.json`, add a top-level `"chef"` key:

```json
"chef": {
    "kitchens": {
        "page_title": "My kitchens",
        "heading": "My kitchens",
        "no_kitchens": "You haven't been assigned to any kitchens yet.",
        "no_dishes": "No dishes in this kitchen yet."
    }
}
```

In `resources/js/i18n/locales/ar.json`, add:

```json
"chef": {
    "kitchens": {
        "page_title": "مطابخي",
        "heading": "مطابخي",
        "no_kitchens": "لم يتم تعيينك لأي مطبخ بعد.",
        "no_dishes": "لا توجد أطباق في هذا المطبخ بعد."
    }
}
```

- [ ] **Step 6: Write `resources/js/pages/chef/kitchens.tsx`**

```tsx
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';

type Dish = { id: number; name_en: string; name_ar: string; price: number };
type Kitchen = { id: number; name_en: string; name_ar: string; dishes: Dish[] };

export default function ChefKitchens({ kitchens }: { kitchens: Kitchen[] }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('chef.kitchens.page_title')} />

            <div className="space-y-6">
                <Heading variant="small" title={t('chef.kitchens.heading')} />

                {kitchens.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        {t('chef.kitchens.no_kitchens')}
                    </p>
                ) : (
                    <div className="space-y-6">
                        {kitchens.map((kitchen) => (
                            <div key={kitchen.id} className="space-y-2 rounded-lg border p-4">
                                <div className="font-medium">{kitchen.name_en}</div>

                                {kitchen.dishes.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('chef.kitchens.no_dishes')}
                                    </p>
                                ) : (
                                    <ul className="space-y-1">
                                        {kitchen.dishes.map((dish) => (
                                            <li key={dish.id} className="flex justify-between text-sm">
                                                <span>{dish.name_en}</span>
                                                <span>{dish.price}</span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
```

- [ ] **Step 7: Format, regenerate wayfinder, type-check**

Run: `vendor/bin/pint --dirty --format agent`
Run: `php artisan wayfinder:generate`
Run: `npm run types:check`
Run: `npm run lint:check`

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --compact --filter=ChefKitchenViewTest`
Expected: PASS (2 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Chef app/Models/Kitchen.php routes/restaurants.php resources/js/pages/chef resources/js/i18n/locales/en.json resources/js/i18n/locales/ar.json resources/js/actions/App/Http/Controllers/Chef resources/js/routes/chef tests/Feature/Management/ChefKitchenViewTest.php
git commit -m "feat: add read-only chef kitchen view"
```

---

### Task 17: Sidebar navigation wiring

**Files:**
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `resources/js/i18n/locales/en.json`
- Modify: `resources/js/i18n/locales/ar.json`

**Interfaces:**
- Consumes: `auth.roles` (Task 1), `companies.index`/`restaurants.index`/`chef.kitchens.index` routes (Tasks 3, 5, 16).

- [ ] **Step 1: Add i18n keys**

In `resources/js/i18n/locales/en.json`, inside `"app.sidebar"`, add:

```json
"companies": "Companies",
"restaurants": "Restaurants",
"my_kitchens": "My kitchens"
```

In `resources/js/i18n/locales/ar.json`, inside `"app.sidebar"`, add:

```json
"companies": "الشركات",
"restaurants": "المطاعم",
"my_kitchens": "مطابخي"
```

- [ ] **Step 2: Add role-based nav items to `app-sidebar.tsx`**

Add imports:

```tsx
import { Building2, ChefHat, UtensilsCrossed } from 'lucide-react';
import { index as companiesIndex } from '@/routes/companies';
import { index as restaurantsIndex } from '@/routes/restaurants';
import { index as chefKitchensIndex } from '@/routes/chef/kitchens';
```

Replace the `mainNavItems` construction:

```tsx
    const roles = page.props.auth.roles;

    const mainNavItems: NavItem[] = [
        {
            title: t('app.sidebar.dashboard'),
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        ...(roles.includes('admin')
            ? [{ title: t('app.sidebar.companies'), href: companiesIndex(), icon: Building2 }]
            : []),
        ...(roles.includes('admin') || roles.includes('manager')
            ? [{ title: t('app.sidebar.restaurants'), href: restaurantsIndex(), icon: UtensilsCrossed }]
            : []),
        ...(roles.includes('chef')
            ? [{ title: t('app.sidebar.my_kitchens'), href: chefKitchensIndex(), icon: ChefHat }]
            : []),
    ];
```

- [ ] **Step 3: Type-check and lint**

Run: `npm run types:check`
Run: `npm run lint:check`

- [ ] **Step 4: Manually verify each role sees the right links**

Run: `npm run build` (or confirm `composer run dev`/`npm run dev` is already running), then sign in as an admin, a manager, and a chef test user (create them via `php artisan tinker` only if no seeded fixtures exist, per the Tinker rule: prefer factories in tests over ad hoc tinker for anything reusable) and confirm the sidebar shows the correct combination of links for each.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/app-sidebar.tsx resources/js/i18n/locales/en.json resources/js/i18n/locales/ar.json
git commit -m "feat: wire role-based navigation for the management UI"
```

---

## Self-Review Notes

- **Spec coverage:** Company (Tasks 3–4), Restaurant + Address (Tasks 5–6), Restaurant Verification (Task 7), Kitchen (Tasks 8–9), Category (Tasks 10–11), Dish (Tasks 12–13), DishOption + ServingSize incl. default-enforcement (Tasks 14–15), Chef read-only view (Task 16), role-based nav (Task 17), shared `auth.roles` prerequisite (Task 1), and the Kitchen/Category/Dish authorization gap from the backend plan (Task 2) are all covered. Customer-facing menu browsing, cart, checkout, payments, and order lifecycle are explicitly out of scope per `idea/00-overview.md` and are not tasks here.
- **Schema-drift guard:** Tasks 12, 14, and 16 each start with a `grep` step against the actual migration files before hardcoding column names, since this plan was written without re-reading the full (truncated) backend plan's Dish/DishOption/ServingSize/ChefKitchen migrations — adjust field names in those tasks' code if the real schema differs from the assumed `name_en`/`name_ar`/`price`/`is_default` shape.
- **Type consistency:** `Dish` always carries `kitchen_id`/`category_id`/`name_en`/`name_ar`/`price` across Tasks 12–15; `ServingSize` always carries `is_default`; route names (`companies.*`, `restaurants.*`, `kitchens.*`, `categories.*`, `dishes.*`, `dish-options.*`, `serving-sizes.*`, `chef.kitchens.index`) are introduced once each and reused consistently in later tasks' frontend code.
