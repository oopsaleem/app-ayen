<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Chef;
use App\Models\Dish;
use App\Models\DishOption;
use App\Models\Kitchen;
use App\Models\Manager;
use App\Models\Restaurant;
use App\Models\RestaurantVerification;
use App\Models\ServingSize;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{restaurant: Restaurant, kitchen: Kitchen, category: Category, manager: User}
 */
function dishManagementFixture(): array
{
    $restaurant = Restaurant::factory()->create();
    $kitchen = Kitchen::factory()->create(['restaurant_id' => $restaurant->id]);
    $category = Category::factory()->create(['restaurant_id' => $restaurant->id]);
    $manager = User::factory()->create();
    Manager::factory()->create(['user_id' => $manager->id, 'company_id' => $restaurant->company_id]);

    return compact('restaurant', 'kitchen', 'category', 'manager');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dishUpdatePayload(Kitchen $kitchen, Category $category, array $overrides = []): array
{
    return [
        'name_en' => 'Dish',
        'name_ar' => 'طبق',
        'price' => 12,
        'kitchen_id' => $kitchen->id,
        'category_id' => $category->id,
        ...$overrides,
    ];
}

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

test('a manager from another company cannot view a restaurants dishes', function () {
    $restaurant = Restaurant::factory()->create();
    $otherManager = User::factory()->create();
    Manager::factory()->create(['user_id' => $otherManager->id]);

    $this->actingAs($otherManager)->get(route('dishes.index', $restaurant))
        ->assertForbidden();
});

test('the dish index provides everything the dish dialog needs', function () {
    ['restaurant' => $restaurant, 'kitchen' => $kitchen, 'category' => $category, 'manager' => $manager] = dishManagementFixture();
    $dish = Dish::factory()->create([
        'kitchen_id' => $kitchen->id,
        'category_id' => $category->id,
        'description_en' => 'Slow cooked lamb',
        'images' => ['dishes/mandi.jpg'],
    ]);
    $dish->options()->create(['name_en' => 'Extra rice', 'name_ar' => 'أرز إضافي', 'price' => 2]);
    $dish->servingSizes()->create(['name_en' => 'Large', 'name_ar' => 'كبير', 'price' => 30, 'is_default' => true, 'servings_count' => 3]);

    $this->actingAs($manager)->get(route('dishes.index', $restaurant))
        ->assertInertia(fn ($page) => $page
            ->component('menu/dishes/index')
            ->has('kitchens', 1)
            ->has('categories', 1)
            ->where('imageLimits.max_images', 5)
            ->where('imageLimits.max_image_bytes', 2048 * 1024)
            ->where('imageLimits.max_request_bytes', UploadedFile::getMaxFilesize())
            ->where('dishes.0.description_en', 'Slow cooked lamb')
            ->where('dishes.0.images', ['dishes/mandi.jpg'])
            ->where('dishes.0.image_urls', [Storage::disk('public')->url('dishes/mandi.jpg')])
            ->where('dishes.0.options.0.name_en', 'Extra rice')
            ->where('dishes.0.serving_sizes.0.servings_count', 3)
        );
});

test('the dish index exposes the public menu link for a verified restaurant', function () {
    ['restaurant' => $restaurant, 'manager' => $manager] = dishManagementFixture();
    RestaurantVerification::factory()->for($restaurant, 'restaurant')->create(['verified' => true]);

    $this->actingAs($manager)->get(route('dishes.index', $restaurant))
        ->assertInertia(fn ($page) => $page
            ->component('menu/dishes/index')
            ->where('restaurant.slug', $restaurant->slug)
            ->where('verified', true)
        );
});

test('the dish index hides the public menu link for an unverified restaurant', function () {
    ['restaurant' => $restaurant, 'manager' => $manager] = dishManagementFixture();

    $this->actingAs($manager)->get(route('dishes.index', $restaurant))
        ->assertInertia(fn ($page) => $page
            ->component('menu/dishes/index')
            ->where('verified', false)
        );
});

test('a chef cannot create a dish', function () {
    ['restaurant' => $restaurant, 'kitchen' => $kitchen, 'category' => $category] = dishManagementFixture();
    $chef = User::factory()->create();
    Chef::factory()->create(['user_id' => $chef->id]);

    $this->actingAs($chef)->post(route('dishes.store', $restaurant), [
        'name_en' => 'Mandi',
        'name_ar' => 'مندي',
        'price' => 25.5,
        'kitchen_id' => $kitchen->id,
        'category_id' => $category->id,
    ])->assertForbidden();

    $this->assertDatabaseMissing('dishes', ['name_en' => 'Mandi']);
});

test('a manager can create a dish with details, images, options and serving sizes', function () {
    Storage::fake('public');
    ['restaurant' => $restaurant, 'kitchen' => $kitchen, 'category' => $category, 'manager' => $manager] = dishManagementFixture();

    $this->actingAs($manager)->post(route('dishes.store', $restaurant), [
        'name_en' => 'Mandi',
        'name_ar' => 'مندي',
        'description_en' => 'Slow cooked lamb',
        'description_ar' => 'لحم مطبوخ ببطء',
        'price' => 25.5,
        'is_available' => '0',
        'kitchen_id' => $kitchen->id,
        'category_id' => $category->id,
        'images' => [UploadedFile::fake()->image('mandi.jpg')],
        'options' => [
            ['name_en' => 'Extra rice', 'name_ar' => 'أرز إضافي', 'price' => 2],
        ],
        'serving_sizes' => [
            ['name_en' => 'Small', 'name_ar' => 'صغير', 'price' => 20, 'is_default' => '0', 'servings_count' => 1],
            ['name_en' => 'Large', 'name_ar' => 'كبير', 'price' => 30, 'is_default' => '1', 'servings_count' => 3],
        ],
    ])->assertRedirect(route('dishes.index', $restaurant));

    $dish = Dish::firstWhere('name_en', 'Mandi');

    expect($dish->description_en)->toBe('Slow cooked lamb')
        ->and($dish->is_available)->toBeFalse()
        ->and($dish->images)->toHaveCount(1)
        ->and($dish->options)->toHaveCount(1)
        ->and($dish->servingSizes)->toHaveCount(2)
        ->and($dish->servingSizes->firstWhere('is_default', true)->name_en)->toBe('Large');

    Storage::disk('public')->assertExists($dish->images[0]);
});

test('a dish is not created when an image cannot be stored', function () {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andReturn(false);
    Storage::set('public', $disk);
    ['restaurant' => $restaurant, 'kitchen' => $kitchen, 'category' => $category, 'manager' => $manager] = dishManagementFixture();

    $this->actingAs($manager)->post(route('dishes.store', $restaurant), [
        'name_en' => 'Mandi',
        'name_ar' => 'مندي',
        'price' => 25.5,
        'kitchen_id' => $kitchen->id,
        'category_id' => $category->id,
        'images' => [UploadedFile::fake()->image('mandi.jpg')],
    ])->assertServerError();

    expect(Dish::count())->toBe(0);
});

test('a manager can add and remove dish images when updating', function () {
    Storage::fake('public');
    ['restaurant' => $restaurant, 'kitchen' => $kitchen, 'category' => $category, 'manager' => $manager] = dishManagementFixture();
    Storage::disk('public')->put('dishes/old.jpg', 'old');
    Storage::disk('public')->put('dishes/kept.jpg', 'kept');
    $dish = Dish::factory()->create([
        'kitchen_id' => $kitchen->id,
        'category_id' => $category->id,
        'images' => ['dishes/old.jpg', 'dishes/kept.jpg'],
    ]);

    $this->actingAs($manager)->post(route('dishes.update', $dish), [
        '_method' => 'PATCH',
        'name_en' => 'Renamed',
        'name_ar' => 'معدل',
        'price' => 12,
        'kitchen_id' => $kitchen->id,
        'category_id' => $category->id,
        'images' => [UploadedFile::fake()->image('new.jpg')],
        'removed_images' => ['dishes/old.jpg'],
    ])->assertRedirect(route('dishes.index', $restaurant));

    $images = $dish->fresh()->images;

    expect($images)->toHaveCount(2)
        ->and($images[0])->toBe('dishes/kept.jpg');
    Storage::disk('public')->assertMissing('dishes/old.jpg');
    Storage::disk('public')->assertExists($images[1]);
});

test('a manager cannot remove a file that is not one of the dishs images', function () {
    Storage::fake('public');
    ['kitchen' => $kitchen, 'category' => $category, 'manager' => $manager] = dishManagementFixture();
    Storage::disk('public')->put('restaurants/logo.jpg', 'logo');
    $dish = Dish::factory()->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id, 'images' => []]);

    $this->actingAs($manager)->post(route('dishes.update', $dish), [
        '_method' => 'PATCH',
        'name_en' => 'Dish',
        'name_ar' => 'طبق',
        'price' => 12,
        'kitchen_id' => $kitchen->id,
        'category_id' => $category->id,
        'removed_images' => ['restaurants/logo.jpg'],
    ])->assertInvalid(['removed_images.0']);

    Storage::disk('public')->assertExists('restaurants/logo.jpg');
});

test('dish images must be image files', function () {
    Storage::fake('public');
    ['restaurant' => $restaurant, 'kitchen' => $kitchen, 'category' => $category, 'manager' => $manager] = dishManagementFixture();

    $this->actingAs($manager)->post(route('dishes.store', $restaurant), [
        'name_en' => 'Mandi',
        'name_ar' => 'مندي',
        'price' => 25.5,
        'kitchen_id' => $kitchen->id,
        'category_id' => $category->id,
        'images' => [UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf')],
    ])->assertInvalid(['images.0']);
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

test('a manager can update, add and remove a dishs options and serving sizes in one save', function () {
    ['restaurant' => $restaurant, 'kitchen' => $kitchen, 'category' => $category, 'manager' => $manager] = dishManagementFixture();
    $dish = Dish::factory()->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    $keptOption = DishOption::factory()->create(['dish_id' => $dish->id, 'name_en' => 'Old name']);
    $removedOption = DishOption::factory()->create(['dish_id' => $dish->id]);
    $smallSize = ServingSize::factory()->create(['dish_id' => $dish->id, 'is_default' => true]);
    $removedSize = ServingSize::factory()->create(['dish_id' => $dish->id, 'is_default' => false]);

    $this->actingAs($manager)->patch(route('dishes.update', $dish), dishUpdatePayload($kitchen, $category, [
        'options' => [
            ['id' => $keptOption->id, 'name_en' => 'Extra rice', 'name_ar' => 'أرز إضافي', 'price' => 2],
            ['name_en' => 'Extra sauce', 'name_ar' => 'صلصة إضافية', 'price' => 1],
        ],
        'serving_sizes' => [
            ['id' => $smallSize->id, 'name_en' => 'Small', 'name_ar' => 'صغير', 'price' => 20, 'is_default' => false, 'servings_count' => 2],
            ['name_en' => 'Large', 'name_ar' => 'كبير', 'price' => 30, 'is_default' => true, 'servings_count' => 3],
        ],
    ]))->assertRedirect(route('dishes.index', $restaurant));

    $dish->refresh();

    expect($dish->options->pluck('name_en')->all())->toEqualCanonicalizing(['Extra rice', 'Extra sauce'])
        ->and($keptOption->fresh()->name_en)->toBe('Extra rice')
        ->and($dish->servingSizes->pluck('name_en')->all())->toEqualCanonicalizing(['Small', 'Large'])
        ->and($smallSize->fresh()->servings_count)->toBe(2)
        ->and($smallSize->fresh()->is_default)->toBeFalse()
        ->and($dish->servingSizes->firstWhere('is_default', true)->name_en)->toBe('Large');
    $this->assertModelMissing($removedOption);
    $this->assertModelMissing($removedSize);
});

test('updating a dish without options or serving sizes removes the ones it had', function () {
    ['restaurant' => $restaurant, 'kitchen' => $kitchen, 'category' => $category, 'manager' => $manager] = dishManagementFixture();
    $dish = Dish::factory()->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    DishOption::factory()->create(['dish_id' => $dish->id]);
    ServingSize::factory()->create(['dish_id' => $dish->id]);

    $this->actingAs($manager)->patch(route('dishes.update', $dish), dishUpdatePayload($kitchen, $category))
        ->assertRedirect(route('dishes.index', $restaurant));

    expect($dish->options()->count())->toBe(0)
        ->and($dish->servingSizes()->count())->toBe(0);
});

test('a dish update cannot touch options or serving sizes of another dish', function () {
    ['kitchen' => $kitchen, 'category' => $category, 'manager' => $manager] = dishManagementFixture();
    $dish = Dish::factory()->create(['kitchen_id' => $kitchen->id, 'category_id' => $category->id]);
    $otherOption = DishOption::factory()->create(['name_en' => 'Untouched']);
    $otherSize = ServingSize::factory()->create(['name_en' => 'Untouched']);

    $this->actingAs($manager)->patch(route('dishes.update', $dish), dishUpdatePayload($kitchen, $category, [
        'options' => [['id' => $otherOption->id, 'name_en' => 'Hijacked', 'name_ar' => 'x', 'price' => 1]],
        'serving_sizes' => [['id' => $otherSize->id, 'name_en' => 'Hijacked', 'name_ar' => 'x', 'price' => 1]],
    ]))->assertInvalid(['options.0.id', 'serving_sizes.0.id']);

    expect($otherOption->fresh()->name_en)->toBe('Untouched')
        ->and($otherSize->fresh()->name_en)->toBe('Untouched');
});

test('a new dish cannot be created with existing option ids', function () {
    ['restaurant' => $restaurant, 'kitchen' => $kitchen, 'category' => $category, 'manager' => $manager] = dishManagementFixture();
    $option = DishOption::factory()->create();

    $this->actingAs($manager)->post(route('dishes.store', $restaurant), dishUpdatePayload($kitchen, $category, [
        'options' => [['id' => $option->id, 'name_en' => 'Extra rice', 'name_ar' => 'أرز', 'price' => 2]],
    ]))->assertInvalid(['options.0.id']);
});
