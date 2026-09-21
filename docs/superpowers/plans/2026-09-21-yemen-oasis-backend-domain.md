# Yemen Oasis Backend Domain (Phase 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the backend domain model for the Yemen Oasis restaurant platform rebuild — identity/roles and the Company → Restaurant → Kitchen → Menu hierarchy — as migrations, Eloquent models, factories, and policies, with no frontend or ordering/payment features yet.

**Architecture:** Single Laravel app, single database, shared tables scoped by `company_id`/`restaurant_id` foreign keys. Tenant/role authorization is enforced via Eloquent policies that walk live relationships (Restaurant → Company → Manager), not a cached permission table. Roles are additive rows (`admins`/`managers`/`chefs` tables keyed by `user_id`), not a single enum column. Bilingual content uses parallel `_en`/`_<secondary>` columns, with a small helper deriving the secondary locale from config rather than hardcoding `'ar'`.

**Tech Stack:** Laravel 13.25 (PHP 8.4), Eloquent, Pest 5.1 (`pestphp/pest-plugin-laravel`), SQLite in-memory for tests (`RefreshDatabase`, already wired globally via `tests/Pest.php`), Laravel Pint for formatting.

**Spec:** [`CONTEXT.md`](../../../CONTEXT.md) (domain glossary) and [`docs/adr/0001`–`0006`](../../adr/) (recorded architecture decisions) at the repo root, plus the source docs in [`idea/`](../../../idea/) that describe the original NestJS/Prisma system this rebuild is based on. This plan implements Phase 1 only — see `idea/00-overview.md` for what's deferred.

## Global Constraints

- PHP 8.4, Laravel 13.25, Pest 5.1 — confirmed via `composer show --direct`; do not use APIs from other major versions.
- No new Composer dependencies — everything in this plan uses packages already installed.
- No data migration from the old NestJS/Prisma system — this is a clean-slate schema (grilling decision, confirmed).
- Multi-tenancy: single DB, shared tables, enforced via Eloquent policies doing a live relationship walk — not a tenancy package, not per-tenant databases (ADR-0004).
- Roles are additive tables (`admins`/`managers`/`chefs`, 1:1 with `users` via `user_id`), not an enum column or a permissions package (ADR-0005). A user may hold more than one role row.
- Company is a new standalone table — it must NOT reuse or reference the existing `teams`/`team_members`/`team_invitations` tables (ADR-0001).
- Categories are scoped per-restaurant (`restaurant_id` FK), not global (ADR-0002).
- No light/dark image array duplication — one `images` (or single `image`) column per entity; theming is a frontend/CSS concern out of scope here (ADR-0003).
- Bilingual fields use literal `_en`/`_ar` columns today, but application code must derive "the secondary field" from `config('app.available_locales')`, never hardcode the string `'ar'` (ADR-0006). `config('app.available_locales')` is already `['en' => 'English', 'ar' => 'العربية']`.
- Always use curly braces for control structures, even single-line bodies. Use PHP 8 constructor property promotion where applicable. Explicit return types and param type hints on every method. TitleCase enum case names. PHPDoc blocks (with array-shape types where relevant) over inline comments.
- Run `vendor/bin/pint --dirty --format agent` after writing/editing PHP files in a task, before running tests, and again before committing if it reformats anything.
- Run tests with `php artisan test --compact --filter=<TestClassName>` for the task's own test; do not run the full suite every task.
- Restaurant verification is tracked (`restaurant_verifications` table) but enforces nothing in Phase 1 — an unverified restaurant is not specially restricted anywhere in this plan (grilling decision, confirmed).
- No uniqueness constraint on Company/Restaurant names (grilling decision, confirmed).

---

## File Structure

```
app/
  Support/
    Locale.php                          # secondary-locale helper (config-driven, no hardcoded 'ar')
  Concerns/
    HasLocalizedFields.php              # trait: localized($field) accessor for bilingual models
  Enums/
    AuthProviderType.php                # Google | Credentials
  Models/
    Company.php
    Admin.php
    Manager.php
    Chef.php
    AuthProvider.php
    Restaurant.php
    RestaurantAddress.php
    RestaurantVerification.php
    Kitchen.php
    ChefKitchen.php                     # pivot model for chef_kitchen
    Category.php                        # self-referential tree, level+cycle-guard logic
    Dish.php
    DishOption.php
    ServingSize.php                     # default-enforcement logic
    User.php                            # MODIFY: add role relations + aggregation helpers
  Policies/
    CompanyPolicy.php
    RestaurantPolicy.php
database/
  migrations/
    2026_09_21_100000_create_companies_table.php
    2026_09_21_100001_create_admins_table.php
    2026_09_21_100002_create_managers_table.php
    2026_09_21_100003_create_chefs_table.php
    2026_09_21_100004_create_auth_providers_table.php
    2026_09_21_100005_create_restaurants_table.php
    2026_09_21_100006_create_restaurant_addresses_table.php
    2026_09_21_100007_create_restaurant_verifications_table.php
    2026_09_21_100008_create_kitchens_table.php
    2026_09_21_100009_create_chef_kitchen_table.php
    2026_09_21_100010_create_categories_table.php
    2026_09_21_100011_create_dishes_table.php
    2026_09_21_100012_create_dish_options_table.php
    2026_09_21_100013_create_serving_sizes_table.php
  factories/
    CompanyFactory.php
    AdminFactory.php
    ManagerFactory.php
    ChefFactory.php
    AuthProviderFactory.php
    RestaurantFactory.php
    RestaurantAddressFactory.php
    RestaurantVerificationFactory.php
    KitchenFactory.php
    CategoryFactory.php
    DishFactory.php
    DishOptionFactory.php
    ServingSizeFactory.php
tests/
  Feature/
    Identity/
      CompanyTest.php
      AdminRoleTest.php
      ManagerRoleTest.php
      ChefRoleTest.php
      AuthProviderTest.php
      UserRoleAggregationTest.php
    Catalog/
      RestaurantTest.php
      RestaurantAddressTest.php
      RestaurantVerificationTest.php
      KitchenTest.php
      ChefKitchenTest.php
    Menu/
      CategoryTest.php
      DishTest.php
      DishOptionTest.php
      ServingSizeTest.php
    Authorization/
      CompanyPolicyTest.php
      RestaurantPolicyTest.php
```

---

### Task 1: Locale helper + bilingual-field accessor trait

**Files:**
- Create: `app/Support/Locale.php`
- Create: `app/Concerns/HasLocalizedFields.php`
- Test: `tests/Feature/Identity/LocaleHelperTest.php`

**Interfaces:**
- Produces: `App\Support\Locale::secondary(): string` — every later model uses this indirectly via the trait.
- Produces: `App\Concerns\HasLocalizedFields::localized(string $field): ?string` — mixed into Restaurant, Kitchen, Category, Dish, DishOption, ServingSize in later tasks.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Support\Locale;

test('secondary locale is derived from config, defaulting to ar today', function () {
    expect(Locale::secondary())->toBe('ar');
});

test('secondary locale falls back to ar when config has only english', function () {
    config(['app.available_locales' => ['en' => 'English']]);

    expect(Locale::secondary())->toBe('ar');
});

test('secondary locale reflects whatever non-english locale is configured', function () {
    config(['app.available_locales' => ['en' => 'English', 'fr' => 'Français']]);

    expect(Locale::secondary())->toBe('fr');
});
```

Save as `tests/Feature/Identity/LocaleHelperTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=LocaleHelperTest`
Expected: FAIL — `Class "App\Support\Locale" not found`.

- [ ] **Step 3: Write the Locale helper**

```php
<?php

namespace App\Support;

class Locale
{
    /**
     * Get the platform's configured secondary (non-English) locale code.
     *
     * Never hardcode 'ar' when deciding which field holds the secondary
     * translation — always go through this helper (see ADR-0006).
     */
    public static function secondary(): string
    {
        $locales = array_keys(config('app.available_locales', []));

        $secondary = collect($locales)->first(fn (string $locale): bool => $locale !== 'en');

        return $secondary ?? 'ar';
    }
}
```

- [ ] **Step 4: Write the HasLocalizedFields trait**

```php
<?php

namespace App\Concerns;

use App\Support\Locale;

trait HasLocalizedFields
{
    /**
     * Get the value of a bilingual field for the current app locale,
     * falling back to the English column when no translation exists.
     */
    public function localized(string $field): ?string
    {
        $suffix = app()->getLocale() === Locale::secondary() ? Locale::secondary() : 'en';

        $value = $this->{"{$field}_{$suffix}"} ?? null;

        if ($value !== null && $value !== '') {
            return $value;
        }

        return $this->{"{$field}_en"};
    }
}
```

- [ ] **Step 5: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=LocaleHelperTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Support/Locale.php app/Concerns/HasLocalizedFields.php tests/Feature/Identity/LocaleHelperTest.php
git commit -m "feat: add locale helper and bilingual-field accessor trait"
```

---

### Task 2: Company

**Files:**
- Create: `database/migrations/2026_09_21_100000_create_companies_table.php`
- Create: `app/Models/Company.php`
- Create: `database/factories/CompanyFactory.php`
- Test: `tests/Feature/Identity/CompanyTest.php`

**Interfaces:**
- Produces: `companies` table (`id`, `display_name`, `description`, timestamps).
- Produces: `App\Models\Company` with `hasMany` `managers` and `restaurants` relations (relations reference `App\Models\Manager` and `App\Models\Restaurant`, created in Tasks 4 and 8 — safe to reference now since `::class` doesn't require the class to exist at parse time).
- Consumes: nothing.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Company;

test('a company can be created with a display name and description', function () {
    $company = Company::factory()->create([
        'display_name' => 'Yemen Oasis Group',
        'description' => 'A family of Yemeni restaurants.',
    ]);

    expect($company->display_name)->toBe('Yemen Oasis Group')
        ->and($company->description)->toBe('A family of Yemeni restaurants.');

    $this->assertDatabaseHas('companies', [
        'id' => $company->id,
        'display_name' => 'Yemen Oasis Group',
    ]);
});
```

Save as `tests/Feature/Identity/CompanyTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CompanyTest`
Expected: FAIL — table `companies` does not exist (or class not found).

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $display_name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Manager> $managers
 * @property-read Collection<int, Restaurant> $restaurants
 */
#[Fillable(['display_name', 'description'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @return HasMany<Manager, $this>
     */
    public function managers(): HasMany
    {
        return $this->hasMany(Manager::class);
    }

    /**
     * @return HasMany<Restaurant, $this>
     */
    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class);
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'display_name' => fake()->unique()->company(),
            'description' => fake()->sentence(),
        ];
    }
}
```

- [ ] **Step 6: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=CompanyTest`
Expected: PASS (1 test)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_21_100000_create_companies_table.php app/Models/Company.php database/factories/CompanyFactory.php tests/Feature/Identity/CompanyTest.php
git commit -m "feat: add Company model"
```

---

### Task 3: Admin role

**Files:**
- Create: `database/migrations/2026_09_21_100001_create_admins_table.php`
- Create: `app/Models/Admin.php`
- Create: `database/factories/AdminFactory.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Identity/AdminRoleTest.php`

**Interfaces:**
- Produces: `admins` table (`id`, `user_id` unique FK, timestamps).
- Produces: `App\Models\Admin` (`belongsTo` User).
- Produces: `User::admin(): HasOne<Admin, $this>` and `User::isAdmin(): bool`.
- Consumes: `App\Models\User` (existing).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Admin;
use App\Models\User;

test('a user becomes an admin by having an admin row', function () {
    $user = User::factory()->create();

    expect($user->isAdmin())->toBeFalse();

    Admin::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->isAdmin())->toBeTrue();
});

test('an admin belongs to its user', function () {
    $user = User::factory()->create();
    $admin = Admin::factory()->create(['user_id' => $user->id]);

    expect($admin->user->id)->toBe($user->id);
});
```

Save as `tests/Feature/Identity/AdminRoleTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AdminRoleTest`
Expected: FAIL — `Class "App\Models\Admin" not found`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id'])]
class Admin extends Model
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
        ];
    }
}
```

- [ ] **Step 6: Add the `admin()` relation and `isAdmin()` helper to User**

In `app/Models/User.php`, add the import and two methods. Find:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
```

Replace with:

```php
use App\Models\Admin;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
```

Then, inside the `User` class, after the `casts()` method's closing brace, add:

```php

    /**
     * @return HasOne<Admin, $this>
     */
    public function admin(): HasOne
    {
        return $this->hasOne(Admin::class);
    }

    /**
     * Determine whether this user holds the Admin role.
     */
    public function isAdmin(): bool
    {
        return $this->admin()->exists();
    }
```

- [ ] **Step 7: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --compact --filter=AdminRoleTest`
Expected: PASS (2 tests)

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_09_21_100001_create_admins_table.php app/Models/Admin.php database/factories/AdminFactory.php app/Models/User.php tests/Feature/Identity/AdminRoleTest.php
git commit -m "feat: add Admin role"
```

---

### Task 4: Manager role

**Files:**
- Create: `database/migrations/2026_09_21_100002_create_managers_table.php`
- Create: `app/Models/Manager.php`
- Create: `database/factories/ManagerFactory.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Identity/ManagerRoleTest.php`

**Interfaces:**
- Produces: `managers` table (`id`, `user_id` unique FK, `company_id` nullable FK, `display_name`, timestamps).
- Produces: `App\Models\Manager` (`belongsTo` User, `belongsTo` Company).
- Produces: `User::manager(): HasOne<Manager, $this>` and `User::isManager(): bool`.
- Consumes: `App\Models\Company` (Task 2), `App\Models\User` (existing, extended in Task 3).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Company;
use App\Models\Manager;
use App\Models\User;

test('a user becomes a manager by having a manager row', function () {
    $user = User::factory()->create();

    expect($user->isManager())->toBeFalse();

    Manager::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->isManager())->toBeTrue();
});

test('a manager can be unassigned from any company', function () {
    $manager = Manager::factory()->create(['company_id' => null]);

    expect($manager->company_id)->toBeNull()
        ->and($manager->company)->toBeNull();
});

test('a manager belongs to exactly one company when assigned', function () {
    $company = Company::factory()->create();
    $manager = Manager::factory()->create(['company_id' => $company->id]);

    expect($manager->company->id)->toBe($company->id);
});
```

Save as `tests/Feature/Identity/ManagerRoleTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ManagerRoleTest`
Expected: FAIL — `Class "App\Models\Manager" not found`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managers');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Database\Factories\ManagerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $company_id
 * @property string $display_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Company|null $company
 */
#[Fillable(['user_id', 'company_id', 'display_name'])]
class Manager extends Model
{
    /** @use HasFactory<ManagerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Manager;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Manager>
 */
class ManagerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'display_name' => fake()->name(),
        ];
    }
}
```

- [ ] **Step 6: Add the `manager()` relation and `isManager()` helper to User**

In `app/Models/User.php`, update the import line added in Task 3:

```php
use App\Models\Admin;
use Illuminate\Database\Eloquent\Relations\HasOne;
```

Replace with:

```php
use App\Models\Admin;
use App\Models\Manager;
use Illuminate\Database\Eloquent\Relations\HasOne;
```

Then, after the `isAdmin()` method added in Task 3, add:

```php

    /**
     * @return HasOne<Manager, $this>
     */
    public function manager(): HasOne
    {
        return $this->hasOne(Manager::class);
    }

    /**
     * Determine whether this user holds the Manager role.
     */
    public function isManager(): bool
    {
        return $this->manager()->exists();
    }
```

- [ ] **Step 7: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --compact --filter=ManagerRoleTest`
Expected: PASS (3 tests)

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_09_21_100002_create_managers_table.php app/Models/Manager.php database/factories/ManagerFactory.php app/Models/User.php tests/Feature/Identity/ManagerRoleTest.php
git commit -m "feat: add Manager role"
```

---

### Task 5: Chef role

**Files:**
- Create: `database/migrations/2026_09_21_100003_create_chefs_table.php`
- Create: `app/Models/Chef.php`
- Create: `database/factories/ChefFactory.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Identity/ChefRoleTest.php`

**Interfaces:**
- Produces: `chefs` table (`id`, `user_id` unique FK, `display_name`, timestamps).
- Produces: `App\Models\Chef` (`belongsTo` User).
- Produces: `User::chef(): HasOne<Chef, $this>` and `User::isChef(): bool`.
- Consumes: `App\Models\User` (extended in Tasks 3–4).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Chef;
use App\Models\User;

test('a user becomes a chef by having a chef row', function () {
    $user = User::factory()->create();

    expect($user->isChef())->toBeFalse();

    Chef::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->isChef())->toBeTrue();
});

test('a chef belongs to its user', function () {
    $user = User::factory()->create();
    $chef = Chef::factory()->create(['user_id' => $user->id]);

    expect($chef->user->id)->toBe($user->id);
});

test('a single user can hold more than one role at once', function () {
    $user = User::factory()->create();

    Chef::factory()->create(['user_id' => $user->id]);
    \App\Models\Manager::factory()->create(['user_id' => $user->id]);

    $fresh = $user->fresh();

    expect($fresh->isChef())->toBeTrue()
        ->and($fresh->isManager())->toBeTrue();
});
```

Save as `tests/Feature/Identity/ChefRoleTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ChefRoleTest`
Expected: FAIL — `Class "App\Models\Chef" not found`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chefs');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Database\Factories\ChefFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $display_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Kitchen> $kitchens
 */
#[Fillable(['user_id', 'display_name'])]
class Chef extends Model
{
    /** @use HasFactory<ChefFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Kitchen, $this, ChefKitchen, 'pivot'>
     */
    public function kitchens(): BelongsToMany
    {
        return $this->belongsToMany(Kitchen::class, 'chef_kitchen')
            ->using(ChefKitchen::class)
            ->withPivot(['assigned_at', 'assigned_by'])
            ->withTimestamps(false);
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Chef;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chef>
 */
class ChefFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => fake()->name(),
        ];
    }
}
```

- [ ] **Step 6: Add the `chef()` relation and `isChef()` helper to User**

In `app/Models/User.php`, update the import block from Task 4:

```php
use App\Models\Admin;
use App\Models\Manager;
use Illuminate\Database\Eloquent\Relations\HasOne;
```

Replace with:

```php
use App\Models\Admin;
use App\Models\Chef;
use App\Models\Manager;
use Illuminate\Database\Eloquent\Relations\HasOne;
```

Then, after the `isManager()` method added in Task 4, add:

```php

    /**
     * @return HasOne<Chef, $this>
     */
    public function chef(): HasOne
    {
        return $this->hasOne(Chef::class);
    }

    /**
     * Determine whether this user holds the Chef role.
     */
    public function isChef(): bool
    {
        return $this->chef()->exists();
    }
```

- [ ] **Step 7: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --compact --filter=ChefRoleTest`
Expected: PASS (3 tests) — note this references `App\Models\Manager`, already built in Task 4, and `App\Models\Kitchen`/`ChefKitchen` by class-string only (not yet created; harmless per PHP `::class` semantics, see Task 8 note).

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_09_21_100003_create_chefs_table.php app/Models/Chef.php database/factories/ChefFactory.php app/Models/User.php tests/Feature/Identity/ChefRoleTest.php
git commit -m "feat: add Chef role"
```

---

### Task 6: AuthProvider + User role aggregation

**Files:**
- Create: `app/Enums/AuthProviderType.php`
- Create: `database/migrations/2026_09_21_100004_create_auth_providers_table.php`
- Create: `app/Models/AuthProvider.php`
- Create: `database/factories/AuthProviderFactory.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Identity/AuthProviderTest.php`
- Test: `tests/Feature/Identity/UserRoleAggregationTest.php`

**Interfaces:**
- Produces: `auth_providers` table (`id`, `user_id` unique FK, `type`, timestamps).
- Produces: `App\Enums\AuthProviderType` (`Google`, `Credentials`).
- Produces: `App\Models\AuthProvider` (`belongsTo` User, `type` cast to enum).
- Produces: `User::authProvider(): HasOne<AuthProvider, $this>`.
- Produces: `User::roles(): array<int, string>` — aggregates `admin`/`manager`/`chef` role names.
- Consumes: `App\Models\Admin`, `App\Models\Manager`, `App\Models\Chef` (Tasks 3–5).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Enums\AuthProviderType;
use App\Models\AuthProvider;
use App\Models\User;

test('a user has an auth provider recording how they signed up', function () {
    $user = User::factory()->create();
    $provider = AuthProvider::factory()->create([
        'user_id' => $user->id,
        'type' => AuthProviderType::Credentials,
    ]);

    expect($user->fresh()->authProvider->type)->toBe(AuthProviderType::Credentials)
        ->and($provider->user->id)->toBe($user->id);
});
```

Save as `tests/Feature/Identity/AuthProviderTest.php`.

```php
<?php

use App\Models\Admin;
use App\Models\Chef;
use App\Models\Manager;
use App\Models\User;

test('a user with no role rows has no roles', function () {
    $user = User::factory()->create();

    expect($user->roles())->toBe([]);
});

test('roles are aggregated across all role tables the user holds', function () {
    $user = User::factory()->create();

    Admin::factory()->create(['user_id' => $user->id]);
    Manager::factory()->create(['user_id' => $user->id]);
    Chef::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->roles())->toBe(['admin', 'manager', 'chef']);
});
```

Save as `tests/Feature/Identity/UserRoleAggregationTest.php`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=AuthProviderTest`
Expected: FAIL — `Class "App\Models\AuthProvider" not found`.

Run: `php artisan test --compact --filter=UserRoleAggregationTest`
Expected: FAIL — `Call to undefined method App\Models\User::roles()`.

- [ ] **Step 3: Write the enum**

```php
<?php

namespace App\Enums;

enum AuthProviderType: string
{
    case Google = 'google';
    case Credentials = 'credentials';
}
```

- [ ] **Step 4: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_providers');
    }
};
```

- [ ] **Step 5: Write the model**

```php
<?php

namespace App\Models;

use App\Enums\AuthProviderType;
use Database\Factories\AuthProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property AuthProviderType $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'type'])]
class AuthProvider extends Model
{
    /** @use HasFactory<AuthProviderFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AuthProviderType::class,
        ];
    }
}
```

- [ ] **Step 6: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Enums\AuthProviderType;
use App\Models\AuthProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthProvider>
 */
class AuthProviderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => AuthProviderType::Credentials,
        ];
    }
}
```

- [ ] **Step 7: Add `authProvider()` relation and `roles()` aggregation to User**

In `app/Models/User.php`, update the import block from Task 5:

```php
use App\Models\Admin;
use App\Models\Chef;
use App\Models\Manager;
use Illuminate\Database\Eloquent\Relations\HasOne;
```

Replace with:

```php
use App\Models\Admin;
use App\Models\AuthProvider;
use App\Models\Chef;
use App\Models\Manager;
use Illuminate\Database\Eloquent\Relations\HasOne;
```

Then, after the `isChef()` method added in Task 5, add:

```php

    /**
     * @return HasOne<AuthProvider, $this>
     */
    public function authProvider(): HasOne
    {
        return $this->hasOne(AuthProvider::class);
    }

    /**
     * Get every role this user currently holds, aggregated across the
     * Admin/Manager/Chef role tables. Roles are additive, not exclusive.
     *
     * @return array<int, string>
     */
    public function roles(): array
    {
        return collect([
            'admin' => $this->isAdmin(),
            'manager' => $this->isManager(),
            'chef' => $this->isChef(),
        ])->filter()->keys()->values()->all();
    }
```

- [ ] **Step 8: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test --compact --filter=AuthProviderTest`
Expected: PASS (1 test)

Run: `php artisan test --compact --filter=UserRoleAggregationTest`
Expected: PASS (2 tests)

- [ ] **Step 10: Commit**

```bash
git add app/Enums/AuthProviderType.php database/migrations/2026_09_21_100004_create_auth_providers_table.php app/Models/AuthProvider.php database/factories/AuthProviderFactory.php app/Models/User.php tests/Feature/Identity/AuthProviderTest.php tests/Feature/Identity/UserRoleAggregationTest.php
git commit -m "feat: add AuthProvider and User role aggregation"
```

---

### Task 7: Restaurant

**Files:**
- Create: `database/migrations/2026_09_21_100005_create_restaurants_table.php`
- Create: `app/Models/Restaurant.php`
- Create: `database/factories/RestaurantFactory.php`
- Test: `tests/Feature/Catalog/RestaurantTest.php`

**Interfaces:**
- Produces: `restaurants` table (`id`, `company_id` FK, `name_en`, `name_ar`, `description_en` nullable, `description_ar` nullable, `images` json nullable, timestamps).
- Produces: `App\Models\Restaurant` using `HasLocalizedFields` (Task 1), `belongsTo` Company (Task 2), `hasMany` Kitchen (referenced by class-string, built in Task 10).
- Consumes: `App\Concerns\HasLocalizedFields` (Task 1), `App\Models\Company` (Task 2).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Company;
use App\Models\Restaurant;

test('a restaurant belongs to a company', function () {
    $company = Company::factory()->create();
    $restaurant = Restaurant::factory()->create(['company_id' => $company->id]);

    expect($restaurant->company->id)->toBe($company->id);
});

test('a restaurant stores bilingual name and description', function () {
    $restaurant = Restaurant::factory()->create([
        'name_en' => 'Yemen Oasis',
        'name_ar' => 'واحة اليمن',
        'description_en' => 'Traditional Yemeni cuisine.',
        'description_ar' => 'مأكولات يمنية تقليدية.',
    ]);

    app()->setLocale('en');
    expect($restaurant->localized('name'))->toBe('Yemen Oasis');

    app()->setLocale('ar');
    expect($restaurant->localized('name'))->toBe('واحة اليمن');
});

test('a restaurant stores a single set of images, not light/dark variants', function () {
    $restaurant = Restaurant::factory()->create([
        'images' => ['https://example.test/one.jpg', 'https://example.test/two.jpg'],
    ]);

    expect($restaurant->fresh()->images)->toBe([
        'https://example.test/one.jpg',
        'https://example.test/two.jpg',
    ]);
});
```

Save as `tests/Feature/Catalog/RestaurantTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=RestaurantTest`
Expected: FAIL — `Class "App\Models\Restaurant" not found`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->json('images')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use App\Concerns\HasLocalizedFields;
use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name_en
 * @property string $name_ar
 * @property string|null $description_en
 * @property string|null $description_ar
 * @property array<int, string>|null $images
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 * @property-read RestaurantAddress|null $address
 * @property-read RestaurantVerification|null $verification
 * @property-read Collection<int, Kitchen> $kitchens
 * @property-read Collection<int, Category> $categories
 */
#[Fillable(['company_id', 'name_en', 'name_ar', 'description_en', 'description_ar', 'images'])]
class Restaurant extends Model
{
    use HasLocalizedFields;

    /** @use HasFactory<RestaurantFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasOne<RestaurantAddress, $this>
     */
    public function address(): HasOne
    {
        return $this->hasOne(RestaurantAddress::class);
    }

    /**
     * @return HasOne<RestaurantVerification, $this>
     */
    public function verification(): HasOne
    {
        return $this->hasOne(RestaurantVerification::class);
    }

    /**
     * @return HasMany<Kitchen, $this>
     */
    public function kitchens(): HasMany
    {
        return $this->hasMany(Kitchen::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'images' => 'array',
        ];
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name_en' => fake()->company(),
            'name_ar' => 'مطعم '.fake()->word(),
            'description_en' => fake()->sentence(),
            'description_ar' => fake()->sentence(),
            'images' => [],
        ];
    }
}
```

- [ ] **Step 6: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=RestaurantTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_21_100005_create_restaurants_table.php app/Models/Restaurant.php database/factories/RestaurantFactory.php tests/Feature/Catalog/RestaurantTest.php
git commit -m "feat: add Restaurant model"
```

---

### Task 8: RestaurantAddress

**Files:**
- Create: `database/migrations/2026_09_21_100006_create_restaurant_addresses_table.php`
- Create: `app/Models/RestaurantAddress.php`
- Create: `database/factories/RestaurantAddressFactory.php`
- Test: `tests/Feature/Catalog/RestaurantAddressTest.php`

**Interfaces:**
- Produces: `restaurant_addresses` table (`id`, `restaurant_id` unique FK, `address` text, `lat`, `lng`, composite index on `(lat, lng)`, timestamps).
- Produces: `App\Models\RestaurantAddress` (`belongsTo` Restaurant).
- Consumes: `App\Models\Restaurant` (Task 7).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Restaurant;
use App\Models\RestaurantAddress;

test('a restaurant has exactly one address', function () {
    $restaurant = Restaurant::factory()->create();
    $address = RestaurantAddress::factory()->create(['restaurant_id' => $restaurant->id]);

    expect($restaurant->fresh()->address->id)->toBe($address->id)
        ->and($address->restaurant->id)->toBe($restaurant->id);
});

test('a restaurant address stores coordinates', function () {
    $address = RestaurantAddress::factory()->create([
        'address' => '123 Main St, Sana\'a',
        'lat' => 15.3547,
        'lng' => 44.2066,
    ]);

    expect((float) $address->lat)->toBe(15.3547)
        ->and((float) $address->lng)->toBe(44.2066);
});

test('a restaurant cannot have two addresses', function () {
    $restaurant = Restaurant::factory()->create();
    RestaurantAddress::factory()->create(['restaurant_id' => $restaurant->id]);

    RestaurantAddress::factory()->create(['restaurant_id' => $restaurant->id]);
})->throws(\Illuminate\Database\QueryException::class);
```

Save as `tests/Feature/Catalog/RestaurantAddressTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=RestaurantAddressTest`
Expected: FAIL — `Class "App\Models\RestaurantAddress" not found`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('address');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->timestamps();

            $table->index(['lat', 'lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_addresses');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Database\Factories\RestaurantAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $restaurant_id
 * @property string $address
 * @property float $lat
 * @property float $lng
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Restaurant $restaurant
 */
#[Fillable(['restaurant_id', 'address', 'lat', 'lng'])]
class RestaurantAddress extends Model
{
    /** @use HasFactory<RestaurantAddressFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantAddress>
 */
class RestaurantAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'address' => fake()->address(),
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
        ];
    }
}
```

- [ ] **Step 6: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=RestaurantAddressTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_21_100006_create_restaurant_addresses_table.php app/Models/RestaurantAddress.php database/factories/RestaurantAddressFactory.php tests/Feature/Catalog/RestaurantAddressTest.php
git commit -m "feat: add RestaurantAddress model"
```

---

### Task 9: RestaurantVerification

**Files:**
- Create: `database/migrations/2026_09_21_100007_create_restaurant_verifications_table.php`
- Create: `app/Models/RestaurantVerification.php`
- Create: `database/factories/RestaurantVerificationFactory.php`
- Test: `tests/Feature/Catalog/RestaurantVerificationTest.php`

**Interfaces:**
- Produces: `restaurant_verifications` table (`restaurant_id` PK/unique FK, `verified_by_admin_id` nullable FK → admins, `verified` boolean default false, timestamps).
- Produces: `App\Models\RestaurantVerification` (`belongsTo` Restaurant, `belongsTo` Admin via `verified_by_admin_id`).
- Consumes: `App\Models\Restaurant` (Task 7), `App\Models\Admin` (Task 3).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Admin;
use App\Models\Restaurant;
use App\Models\RestaurantVerification;

test('a restaurant verification defaults to unverified', function () {
    $restaurant = Restaurant::factory()->create();
    $verification = RestaurantVerification::factory()->create([
        'restaurant_id' => $restaurant->id,
        'verified' => false,
        'verified_by_admin_id' => null,
    ]);

    expect($verification->verified)->toBeFalse()
        ->and($restaurant->fresh()->verification->id)->toBe($verification->id);
});

test('a restaurant verification records which admin verified it', function () {
    $admin = Admin::factory()->create();
    $verification = RestaurantVerification::factory()->create([
        'verified_by_admin_id' => $admin->id,
        'verified' => true,
    ]);

    expect($verification->verifiedByAdmin->id)->toBe($admin->id)
        ->and($verification->verified)->toBeTrue();
});
```

Save as `tests/Feature/Catalog/RestaurantVerificationTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=RestaurantVerificationTest`
Expected: FAIL — `Class "App\Models\RestaurantVerification" not found`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_verifications', function (Blueprint $table) {
            $table->foreignId('restaurant_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('verified_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->boolean('verified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_verifications');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Database\Factories\RestaurantVerificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $restaurant_id
 * @property int|null $verified_by_admin_id
 * @property bool $verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Restaurant $restaurant
 * @property-read Admin|null $verifiedByAdmin
 */
#[Fillable(['restaurant_id', 'verified_by_admin_id', 'verified'])]
class RestaurantVerification extends Model
{
    /** @use HasFactory<RestaurantVerificationFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $primaryKey = 'restaurant_id';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'verified_by_admin_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
        ];
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantVerification>
 */
class RestaurantVerificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'verified_by_admin_id' => null,
            'verified' => false,
        ];
    }
}
```

- [ ] **Step 6: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=RestaurantVerificationTest`
Expected: PASS (2 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_21_100007_create_restaurant_verifications_table.php app/Models/RestaurantVerification.php database/factories/RestaurantVerificationFactory.php tests/Feature/Catalog/RestaurantVerificationTest.php
git commit -m "feat: add RestaurantVerification model"
```

---

### Task 10: Kitchen

**Files:**
- Create: `database/migrations/2026_09_21_100008_create_kitchens_table.php`
- Create: `app/Models/Kitchen.php`
- Create: `database/factories/KitchenFactory.php`
- Test: `tests/Feature/Catalog/KitchenTest.php`

**Interfaces:**
- Produces: `kitchens` table (`id`, `restaurant_id` FK, `name_en`, `name_ar`, timestamps).
- Produces: `App\Models\Kitchen` using `HasLocalizedFields`, `belongsTo` Restaurant, `hasMany` Dish (class-string reference, built Task 13), `belongsToMany` Chef.
- Consumes: `App\Concerns\HasLocalizedFields` (Task 1), `App\Models\Restaurant` (Task 7).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Kitchen;
use App\Models\Restaurant;

test('a kitchen belongs to a restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);

    expect($kitchen->restaurant->id)->toBe($restaurant->id)
        ->and($restaurant->fresh()->kitchens)->toHaveCount(1);
});

test('a kitchen stores a bilingual name', function () {
    $kitchen = Kitchen::factory()->create([
        'name_en' => 'Grill',
        'name_ar' => 'شواية',
    ]);

    app()->setLocale('en');
    expect($kitchen->localized('name'))->toBe('Grill');

    app()->setLocale('ar');
    expect($kitchen->localized('name'))->toBe('شواية');
});
```

Save as `tests/Feature/Catalog/KitchenTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=KitchenTest`
Expected: FAIL — `Class "App\Models\Kitchen" not found`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_ar');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchens');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use App\Concerns\HasLocalizedFields;
use Database\Factories\KitchenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $restaurant_id
 * @property string $name_en
 * @property string $name_ar
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Restaurant $restaurant
 * @property-read Collection<int, Dish> $dishes
 * @property-read Collection<int, Chef> $chefs
 */
#[Fillable(['restaurant_id', 'name_en', 'name_ar'])]
class Kitchen extends Model
{
    use HasLocalizedFields;

    /** @use HasFactory<KitchenFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return HasMany<Dish, $this>
     */
    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    /**
     * @return BelongsToMany<Chef, $this, ChefKitchen, 'pivot'>
     */
    public function chefs(): BelongsToMany
    {
        return $this->belongsToMany(Chef::class, 'chef_kitchen')
            ->using(ChefKitchen::class)
            ->withPivot(['assigned_at', 'assigned_by'])
            ->withTimestamps(false);
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Kitchen;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kitchen>
 */
class KitchenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name_en' => fake()->randomElement(['Grill', 'Bakery', 'Cold Kitchen', 'Dessert Station']),
            'name_ar' => fake()->randomElement(['شواية', 'مخبز', 'مطبخ بارد', 'محطة الحلويات']),
        ];
    }
}
```

- [ ] **Step 6: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=KitchenTest`
Expected: PASS (2 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_21_100008_create_kitchens_table.php app/Models/Kitchen.php database/factories/KitchenFactory.php tests/Feature/Catalog/KitchenTest.php
git commit -m "feat: add Kitchen model"
```

---

### Task 11: ChefKitchen pivot

**Files:**
- Create: `database/migrations/2026_09_21_100009_create_chef_kitchen_table.php`
- Create: `app/Models/ChefKitchen.php`
- Test: `tests/Feature/Catalog/ChefKitchenTest.php`

**Interfaces:**
- Produces: `chef_kitchen` table (`chef_id`, `kitchen_id` composite PK, cascade delete both ways, `assigned_at` timestamp nullable, `assigned_by` nullable FK → users).
- Produces: `App\Models\ChefKitchen` (Pivot model, `belongsTo` Chef, Kitchen, and User via `assigned_by`).
- Consumes: `App\Models\Chef` (Task 5), `App\Models\Kitchen` (Task 10), `App\Models\User` (existing).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Chef;
use App\Models\Kitchen;
use App\Models\User;

test('a chef can be assigned to a kitchen with assignment metadata', function () {
    $chef = Chef::factory()->create();
    $kitchen = Kitchen::factory()->create();
    $manager = User::factory()->create();
    $assignedAt = now();

    $chef->kitchens()->attach($kitchen->id, [
        'assigned_at' => $assignedAt,
        'assigned_by' => $manager->id,
    ]);

    $pivot = $chef->fresh()->kitchens->first()->pivot;

    expect($chef->fresh()->kitchens)->toHaveCount(1)
        ->and($pivot->assigned_by)->toBe($manager->id)
        ->and($kitchen->fresh()->chefs)->toHaveCount(1);
});

test('deleting a chef removes their kitchen assignments', function () {
    $chef = Chef::factory()->create();
    $kitchen = Kitchen::factory()->create();

    $chef->kitchens()->attach($kitchen->id, ['assigned_at' => now()]);

    $chef->delete();

    expect(\Illuminate\Support\Facades\DB::table('chef_kitchen')->count())->toBe(0);
});

test('deleting a kitchen removes its chef assignments', function () {
    $chef = Chef::factory()->create();
    $kitchen = Kitchen::factory()->create();

    $chef->kitchens()->attach($kitchen->id, ['assigned_at' => now()]);

    $kitchen->delete();

    expect(\Illuminate\Support\Facades\DB::table('chef_kitchen')->count())->toBe(0);
});
```

Save as `tests/Feature/Catalog/ChefKitchenTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ChefKitchenTest`
Expected: FAIL — table `chef_kitchen` does not exist.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chef_kitchen', function (Blueprint $table) {
            $table->foreignId('chef_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->primary(['chef_id', 'kitchen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chef_kitchen');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $chef_id
 * @property int $kitchen_id
 * @property \Illuminate\Support\Carbon|null $assigned_at
 * @property int|null $assigned_by
 * @property-read Chef $chef
 * @property-read Kitchen $kitchen
 * @property-read User|null $assignedBy
 */
#[Fillable(['chef_id', 'kitchen_id', 'assigned_at', 'assigned_by'])]
class ChefKitchen extends Pivot
{
    /**
     * @var string
     */
    protected $table = 'chef_kitchen';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return BelongsTo<Chef, $this>
     */
    public function chef(): BelongsTo
    {
        return $this->belongsTo(Chef::class);
    }

    /**
     * @return BelongsTo<Kitchen, $this>
     */
    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }
}
```

- [ ] **Step 5: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=ChefKitchenTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_21_100009_create_chef_kitchen_table.php app/Models/ChefKitchen.php tests/Feature/Catalog/ChefKitchenTest.php
git commit -m "feat: add ChefKitchen pivot with assignment metadata"
```

---

### Task 12: Category (self-referential tree, level + cycle guard)

**Files:**
- Create: `database/migrations/2026_09_21_100010_create_categories_table.php`
- Create: `app/Models/Category.php`
- Create: `database/factories/CategoryFactory.php`
- Test: `tests/Feature/Menu/CategoryTest.php`

**Interfaces:**
- Produces: `categories` table (`id`, `restaurant_id` FK, `name_en`, `name_ar`, `description_en`/`description_ar` nullable, `image` nullable, `is_active` default true, `level` default 1, `parent_id` nullable self-FK, `order` default 0, timestamps).
- Produces: `App\Models\Category` using `HasLocalizedFields`, self-referential `parent()`/`children()`, application-computed `level`, cycle guard on save.
- Consumes: `App\Concerns\HasLocalizedFields` (Task 1), `App\Models\Restaurant` (Task 7).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Category;
use App\Models\Restaurant;

test('a top-level category has level 1', function () {
    $category = Category::factory()->create(['parent_id' => null]);

    expect($category->level)->toBe(1);
});

test('a subcategory level is computed as parent level plus one', function () {
    $restaurant = Restaurant::factory()->create();
    $parent = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => null]);
    $child = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $parent->id]);

    expect($child->fresh()->level)->toBe(2);

    $grandchild = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $child->id]);

    expect($grandchild->fresh()->level)->toBe(3);
});

test('level is recomputed when a category is reparented', function () {
    $restaurant = Restaurant::factory()->create();
    $topA = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => null]);
    $topB = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => null]);
    $child = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $topA->id]);

    expect($child->fresh()->level)->toBe(2);

    $grandchild = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $child->id]);
    expect($grandchild->fresh()->level)->toBe(3);

    $child->update(['parent_id' => $topB->id]);

    expect($child->fresh()->level)->toBe(2);
});

test('a category cannot be made its own parent', function () {
    $category = Category::factory()->create();

    $category->update(['parent_id' => $category->id]);
})->throws(\InvalidArgumentException::class);

test('a category cannot be reparented under its own descendant', function () {
    $restaurant = Restaurant::factory()->create();
    $top = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => null]);
    $child = Category::factory()->create(['restaurant_id' => $restaurant->id, 'parent_id' => $top->id]);

    $top->update(['parent_id' => $child->id]);
})->throws(\InvalidArgumentException::class);

test('a category belongs to exactly one restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);

    expect($category->restaurant->id)->toBe($restaurant->id);
});
```

Save as `tests/Feature/Menu/CategoryTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CategoryTest`
Expected: FAIL — `Class "App\Models\Category" not found`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('level')->default(1);
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use App\Concerns\HasLocalizedFields;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $restaurant_id
 * @property string $name_en
 * @property string $name_ar
 * @property string|null $description_en
 * @property string|null $description_ar
 * @property string|null $image
 * @property bool $is_active
 * @property int $level
 * @property int|null $parent_id
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Restaurant $restaurant
 * @property-read Category|null $parent
 * @property-read Collection<int, Category> $children
 * @property-read Collection<int, Dish> $dishes
 */
#[Fillable([
    'restaurant_id', 'name_en', 'name_ar', 'description_en', 'description_ar',
    'image', 'is_active', 'parent_id', 'order',
])]
class Category extends Model
{
    use HasLocalizedFields;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Category $category) {
            $category->level = $category->parent_id
                ? static::findOrFail($category->parent_id)->level + 1
                : 1;

            if ($category->parent_id) {
                $category->guardAgainstCycle();
            }
        });
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Dish, $this>
     */
    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    /**
     * Reject a parent_id change that would make this category its own
     * ancestor (directly or transitively).
     */
    protected function guardAgainstCycle(): void
    {
        $ancestorId = $this->parent_id;
        $visited = [];

        while ($ancestorId !== null) {
            if ($ancestorId === $this->id) {
                throw new InvalidArgumentException('A category cannot be its own ancestor.');
            }

            if (in_array($ancestorId, $visited, true)) {
                break;
            }

            $visited[] = $ancestorId;
            $ancestorId = static::whereKey($ancestorId)->value('parent_id');
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name_en' => fake()->words(2, true),
            'name_ar' => 'فئة '.fake()->word(),
            'description_en' => fake()->sentence(),
            'description_ar' => fake()->sentence(),
            'image' => null,
            'is_active' => true,
            'parent_id' => null,
            'order' => 0,
        ];
    }
}
```

- [ ] **Step 6: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=CategoryTest`
Expected: PASS (6 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_21_100010_create_categories_table.php app/Models/Category.php database/factories/CategoryFactory.php tests/Feature/Menu/CategoryTest.php
git commit -m "feat: add Category model with level computation and cycle guard"
```

---

### Task 13: Dish

**Files:**
- Create: `database/migrations/2026_09_21_100011_create_dishes_table.php`
- Create: `app/Models/Dish.php`
- Create: `database/factories/DishFactory.php`
- Test: `tests/Feature/Menu/DishTest.php`

**Interfaces:**
- Produces: `dishes` table (`id`, `category_id` FK, `kitchen_id` FK, `name_en`, `name_ar`, `description_en`/`description_ar` nullable, `price` decimal, `is_available` default true, `images` json nullable, timestamps).
- Produces: `App\Models\Dish` using `HasLocalizedFields`, `belongsTo` Category and Kitchen, `hasMany` DishOption and ServingSize (class-string references, built Tasks 14–15).
- Consumes: `App\Concerns\HasLocalizedFields` (Task 1), `App\Models\Category` (Task 12), `App\Models\Kitchen` (Task 10).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Category;
use App\Models\Dish;
use App\Models\Kitchen;

test('a dish belongs to a category and a kitchen', function () {
    $category = Category::factory()->create();
    $kitchen = Kitchen::factory()->create();
    $dish = Dish::factory()->create([
        'category_id' => $category->id,
        'kitchen_id' => $kitchen->id,
    ]);

    expect($dish->category->id)->toBe($category->id)
        ->and($dish->kitchen->id)->toBe($kitchen->id);
});

test('a dish can be toggled unavailable without being deleted', function () {
    $dish = Dish::factory()->create(['is_available' => true]);

    $dish->update(['is_available' => false]);

    expect($dish->fresh())->not->toBeNull()
        ->and($dish->fresh()->is_available)->toBeFalse();
});

test('a dish stores a single image set, not light/dark variants', function () {
    $dish = Dish::factory()->create([
        'images' => ['https://example.test/dish.jpg'],
    ]);

    expect($dish->fresh()->images)->toBe(['https://example.test/dish.jpg']);
});
```

Save as `tests/Feature/Menu/DishTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DishTest`
Expected: FAIL — `Class "App\Models\Dish" not found`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dishes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_id')->constrained()->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->decimal('price', 8, 2);
            $table->boolean('is_available')->default(true);
            $table->json('images')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dishes');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use App\Concerns\HasLocalizedFields;
use Database\Factories\DishFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $category_id
 * @property int $kitchen_id
 * @property string $name_en
 * @property string $name_ar
 * @property string|null $description_en
 * @property string|null $description_ar
 * @property string $price
 * @property bool $is_available
 * @property array<int, string>|null $images
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category $category
 * @property-read Kitchen $kitchen
 * @property-read Collection<int, DishOption> $options
 * @property-read Collection<int, ServingSize> $servingSizes
 */
#[Fillable([
    'category_id', 'kitchen_id', 'name_en', 'name_ar', 'description_en',
    'description_ar', 'price', 'is_available', 'images',
])]
class Dish extends Model
{
    use HasLocalizedFields;

    /** @use HasFactory<DishFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Kitchen, $this>
     */
    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    /**
     * @return HasMany<DishOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(DishOption::class);
    }

    /**
     * @return HasMany<ServingSize, $this>
     */
    public function servingSizes(): HasMany
    {
        return $this->hasMany(ServingSize::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'images' => 'array',
        ];
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Kitchen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dish>
 */
class DishFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'kitchen_id' => Kitchen::factory(),
            'name_en' => fake()->words(3, true),
            'name_ar' => 'طبق '.fake()->word(),
            'description_en' => fake()->sentence(),
            'description_ar' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 5, 50),
            'is_available' => true,
            'images' => [],
        ];
    }
}
```

- [ ] **Step 6: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=DishTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_21_100011_create_dishes_table.php app/Models/Dish.php database/factories/DishFactory.php tests/Feature/Menu/DishTest.php
git commit -m "feat: add Dish model"
```

---

### Task 14: DishOption (with availability toggle)

**Files:**
- Create: `database/migrations/2026_09_21_100012_create_dish_options_table.php`
- Create: `app/Models/DishOption.php`
- Create: `database/factories/DishOptionFactory.php`
- Test: `tests/Feature/Menu/DishOptionTest.php`

**Interfaces:**
- Produces: `dish_options` table (`id`, `dish_id` FK, `name_en`, `name_ar`, `price`, `is_available` default true, timestamps).
- Produces: `App\Models\DishOption` using `HasLocalizedFields`, `belongsTo` Dish.
- Consumes: `App\Concerns\HasLocalizedFields` (Task 1), `App\Models\Dish` (Task 13).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Dish;
use App\Models\DishOption;

test('a dish option belongs to a dish', function () {
    $dish = Dish::factory()->create();
    $option = DishOption::factory()->create(['dish_id' => $dish->id]);

    expect($option->dish->id)->toBe($dish->id);
});

test('a dish option defaults to available', function () {
    $option = DishOption::factory()->create();

    expect($option->is_available)->toBeTrue();
});

test('a dish option can be disabled without being deleted', function () {
    $option = DishOption::factory()->create(['is_available' => true]);

    $option->update(['is_available' => false]);

    $this->assertDatabaseHas('dish_options', [
        'id' => $option->id,
        'is_available' => false,
    ]);
});
```

Save as `tests/Feature/Menu/DishOptionTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DishOptionTest`
Expected: FAIL — `Class "App\Models\DishOption" not found`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dish_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_id')->constrained()->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_ar');
            $table->decimal('price', 8, 2);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dish_options');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use App\Concerns\HasLocalizedFields;
use Database\Factories\DishOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $dish_id
 * @property string $name_en
 * @property string $name_ar
 * @property string $price
 * @property bool $is_available
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Dish $dish
 */
#[Fillable(['dish_id', 'name_en', 'name_ar', 'price', 'is_available'])]
class DishOption extends Model
{
    use HasLocalizedFields;

    /** @use HasFactory<DishOptionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Dish, $this>
     */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\DishOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DishOption>
 */
class DishOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dish_id' => Dish::factory(),
            'name_en' => fake()->randomElement(['Extra cheese', 'Spicy', 'No onions', 'Extra sauce']),
            'name_ar' => fake()->randomElement(['جبنة اضافية', 'حار', 'بدون بصل', 'صلصة اضافية']),
            'price' => fake()->randomFloat(2, 0.5, 10),
            'is_available' => true,
        ];
    }
}
```

- [ ] **Step 6: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=DishOptionTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_21_100012_create_dish_options_table.php app/Models/DishOption.php database/factories/DishOptionFactory.php tests/Feature/Menu/DishOptionTest.php
git commit -m "feat: add DishOption model with availability toggle"
```

---

### Task 15: ServingSize (with single-default enforcement)

**Files:**
- Create: `database/migrations/2026_09_21_100013_create_serving_sizes_table.php`
- Create: `app/Models/ServingSize.php`
- Create: `database/factories/ServingSizeFactory.php`
- Test: `tests/Feature/Menu/ServingSizeTest.php`

**Interfaces:**
- Produces: `serving_sizes` table (`id`, `dish_id` FK, `name_en`, `name_ar`, `price`, `is_default` default false, `servings_count` default 1, timestamps).
- Produces: `App\Models\ServingSize` using `HasLocalizedFields`, `belongsTo` Dish, application-level enforcement that at most one serving size per dish is `is_default`.
- Consumes: `App\Concerns\HasLocalizedFields` (Task 1), `App\Models\Dish` (Task 13).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Dish;
use App\Models\ServingSize;

test('a serving size belongs to a dish', function () {
    $dish = Dish::factory()->create();
    $servingSize = ServingSize::factory()->create(['dish_id' => $dish->id]);

    expect($servingSize->dish->id)->toBe($dish->id);
});

test('marking a serving size default unsets the previous default for that dish', function () {
    $dish = Dish::factory()->create();
    $small = ServingSize::factory()->create(['dish_id' => $dish->id, 'is_default' => true]);
    $large = ServingSize::factory()->create(['dish_id' => $dish->id, 'is_default' => false]);

    $large->update(['is_default' => true]);

    expect($small->fresh()->is_default)->toBeFalse()
        ->and($large->fresh()->is_default)->toBeTrue();
});

test('serving sizes on different dishes do not affect each other default flag', function () {
    $dishA = Dish::factory()->create();
    $dishB = Dish::factory()->create();

    $sizeA = ServingSize::factory()->create(['dish_id' => $dishA->id, 'is_default' => true]);
    $sizeB = ServingSize::factory()->create(['dish_id' => $dishB->id, 'is_default' => true]);

    expect($sizeA->fresh()->is_default)->toBeTrue()
        ->and($sizeB->fresh()->is_default)->toBeTrue();
});
```

Save as `tests/Feature/Menu/ServingSizeTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ServingSizeTest`
Expected: FAIL — `Class "App\Models\ServingSize" not found`.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serving_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_id')->constrained()->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_ar');
            $table->decimal('price', 8, 2);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('servings_count')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serving_sizes');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use App\Concerns\HasLocalizedFields;
use Database\Factories\ServingSizeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $dish_id
 * @property string $name_en
 * @property string $name_ar
 * @property string $price
 * @property bool $is_default
 * @property int $servings_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Dish $dish
 */
#[Fillable(['dish_id', 'name_en', 'name_ar', 'price', 'is_default', 'servings_count'])]
class ServingSize extends Model
{
    use HasLocalizedFields;

    /** @use HasFactory<ServingSizeFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(function (ServingSize $servingSize) {
            if (! $servingSize->is_default) {
                return;
            }

            static::where('dish_id', $servingSize->dish_id)
                ->whereKeyNot($servingSize->id)
                ->update(['is_default' => false]);
        });
    }

    /**
     * @return BelongsTo<Dish, $this>
     */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\ServingSize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServingSize>
 */
class ServingSizeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dish_id' => Dish::factory(),
            'name_en' => fake()->randomElement(['Small', 'Medium', 'Large']),
            'name_ar' => fake()->randomElement(['صغير', 'وسط', 'كبير']),
            'price' => fake()->randomFloat(2, 5, 50),
            'is_default' => false,
            'servings_count' => 1,
        ];
    }
}
```

- [ ] **Step 6: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=ServingSizeTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_21_100013_create_serving_sizes_table.php app/Models/ServingSize.php database/factories/ServingSizeFactory.php tests/Feature/Menu/ServingSizeTest.php
git commit -m "feat: add ServingSize model with single-default enforcement"
```

---

### Task 16: CompanyPolicy

**Files:**
- Create: `app/Policies/CompanyPolicy.php`
- Test: `tests/Feature/Authorization/CompanyPolicyTest.php`

**Interfaces:**
- Produces: `App\Policies\CompanyPolicy` with `view(User, Company): bool` and `update(User, Company): bool`.
- Consumes: `App\Models\User::isAdmin()` (Task 3), `App\Models\User::manager()` (Task 4), `App\Models\Company` (Task 2).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Admin;
use App\Models\Company;
use App\Models\Manager;
use App\Models\User;

test('an admin can view and update any company', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $company = Company::factory()->create();

    expect($admin->can('view', $company))->toBeTrue()
        ->and($admin->can('update', $company))->toBeTrue();
});

test('a manager can view and update only their own company', function () {
    $ownCompany = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $user = User::factory()->create();
    Manager::factory()->create(['user_id' => $user->id, 'company_id' => $ownCompany->id]);

    expect($user->can('view', $ownCompany))->toBeTrue()
        ->and($user->can('update', $ownCompany))->toBeTrue()
        ->and($user->can('view', $otherCompany))->toBeFalse()
        ->and($user->can('update', $otherCompany))->toBeFalse();
});

test('a user with no admin or manager role cannot view or update a company', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    expect($user->can('view', $company))->toBeFalse()
        ->and($user->can('update', $company))->toBeFalse();
});

test('an unassigned manager cannot view or update any company', function () {
    $user = User::factory()->create();
    Manager::factory()->create(['user_id' => $user->id, 'company_id' => null]);
    $company = Company::factory()->create();

    expect($user->can('view', $company))->toBeFalse()
        ->and($user->can('update', $company))->toBeFalse();
});
```

Save as `tests/Feature/Authorization/CompanyPolicyTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CompanyPolicyTest`
Expected: FAIL — all assertions return `false`/no policy registered (Laravel auto-discovers `{Model}Policy` by convention, so once the file exists it's picked up automatically; before that every `can()` call falls through to `false`).

- [ ] **Step 3: Write the policy**

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
     * Determine whether the user can update the company.
     */
    public function update(User $user, Company $company): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $company->id;
    }
}
```

- [ ] **Step 4: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=CompanyPolicyTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Policies/CompanyPolicy.php tests/Feature/Authorization/CompanyPolicyTest.php
git commit -m "feat: add CompanyPolicy"
```

---

### Task 17: RestaurantPolicy

**Files:**
- Create: `app/Policies/RestaurantPolicy.php`
- Test: `tests/Feature/Authorization/RestaurantPolicyTest.php`

**Interfaces:**
- Produces: `App\Policies\RestaurantPolicy` with `view(User, Restaurant): bool` (always true — Phase 1 does not gate browsing on verification, per grilling decision), `update(User, Restaurant): bool` (admin or the restaurant's own company's manager), `verify(User, Restaurant): bool` (admin only).
- Consumes: `App\Models\User::isAdmin()` (Task 3), `App\Models\User::manager()` (Task 4), `App\Models\Restaurant` (Task 7).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Admin;
use App\Models\Company;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\User;

test('any authenticated user can view any restaurant', function () {
    $user = User::factory()->create();
    $restaurant = Restaurant::factory()->create();

    expect($user->can('view', $restaurant))->toBeTrue();
});

test('a manager can update only restaurants owned by their company', function () {
    $ownCompany = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $user = User::factory()->create();
    Manager::factory()->create(['user_id' => $user->id, 'company_id' => $ownCompany->id]);

    $ownRestaurant = Restaurant::factory()->create(['company_id' => $ownCompany->id]);
    $otherRestaurant = Restaurant::factory()->create(['company_id' => $otherCompany->id]);

    expect($user->can('update', $ownRestaurant))->toBeTrue()
        ->and($user->can('update', $otherRestaurant))->toBeFalse();
});

test('an admin can update any restaurant', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);
    $restaurant = Restaurant::factory()->create();

    expect($admin->can('update', $restaurant))->toBeTrue();
});

test('only an admin can verify a restaurant', function () {
    $admin = User::factory()->create();
    Admin::factory()->create(['user_id' => $admin->id]);

    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id]);

    $restaurant = Restaurant::factory()->create();

    expect($admin->can('verify', $restaurant))->toBeTrue()
        ->and($manager->can('verify', $restaurant))->toBeFalse();
});
```

Save as `tests/Feature/Authorization/RestaurantPolicyTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=RestaurantPolicyTest`
Expected: FAIL — no policy registered yet, all `can()` calls return `false`, including the "any user can view" case.

- [ ] **Step 3: Write the policy**

```php
<?php

namespace App\Policies;

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
     * Determine whether the user can update the restaurant.
     */
    public function update(User $user, Restaurant $restaurant): bool
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

- [ ] **Step 4: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=RestaurantPolicyTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Policies/RestaurantPolicy.php tests/Feature/Authorization/RestaurantPolicyTest.php
git commit -m "feat: add RestaurantPolicy"
```

---

### Task 18: Full backend test suite + migration sanity check

**Files:**
- None created — verification-only task.

**Interfaces:**
- Consumes: every model, migration, factory, and policy from Tasks 1–17.
- Produces: confidence that the full Phase 1 backend domain works together (migrations run clean, full suite passes).

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test --compact`
Expected: PASS — every test from Tasks 1–17, plus the pre-existing Teams/Auth/Settings suite, all green.

- [ ] **Step 2: Verify migrations run cleanly from empty**

Run: `php artisan migrate:fresh --env=testing`
Expected: All 14 new migrations (plus the pre-existing ones) run without error, in dependency order (companies → admins → managers → chefs → auth_providers → restaurants → restaurant_addresses → restaurant_verifications → kitchens → chef_kitchen → categories → dishes → dish_options → serving_sizes).

- [ ] **Step 3: Run Larastan static analysis on the new code**

Run: `vendor/bin/phpstan analyse app/Models app/Policies app/Support app/Concerns app/Enums`
Expected: No errors. If Larastan flags a relation return-type mismatch, fix the PHPDoc `@return` generic to match the actual related model before proceeding.

- [ ] **Step 4: Commit (if Step 3 required fixes)**

```bash
git add -A
git commit -m "fix: address static analysis findings in backend domain models"
```

If Step 3 found nothing to fix, skip this commit — there's nothing to record.

---

## Self-Review Notes

- **Spec coverage:** Every entity in `CONTEXT.md` (Company, Restaurant, Kitchen, Category, Dish, DishOption, ServingSize, Admin, Manager, Chef, Verification) has a task. Every ADR (0001–0006) is reflected: Company≠Team (Task 2, no Team references anywhere), per-restaurant categories (Task 12, `restaurant_id` FK), no light/dark images (Tasks 7/13, single `images` column), single-DB app-scoped tenancy (Tasks 16–17, policies), additive role tables (Tasks 3–6), config-driven secondary locale (Task 1, used throughout via `HasLocalizedFields`). `Customer` is intentionally not a table (no role row = customer), matching the glossary.
- **Explicitly out of scope, confirmed absent from this plan:** Order, OrderDish, OrderDishOption, OrderStatusHistory, OrderStatusThreshold, DeliveryAddress, Review, Alert, Notification, Stripe, cart/checkout, chef-facing kitchen view, Google OAuth/Socialite (`AuthProviderType::Google` case exists as a documented-but-unused enum case, matching how the original schema anticipated features it didn't wire up).
- **Placeholder scan:** No TBD/TODO markers; every step has runnable code or an exact command.
- **Type consistency:** `HasLocalizedFields::localized()` used identically across Restaurant, Kitchen, Category, Dish, DishOption, ServingSize. `AuthProviderType` enum used consistently in migration (`string` column), model cast, and factory. Policy method names (`view`/`update`/`verify`) match what a later frontend plan's `Gate::authorize()` calls will need.

---

**Plan complete and saved to `docs/superpowers/plans/2026-09-21-yemen-oasis-backend-domain.md`.** Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
