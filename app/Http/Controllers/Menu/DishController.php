<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\SaveDishRequest;
use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class DishController extends Controller
{
    /**
     * The disk that stores uploaded dish images.
     */
    private const IMAGE_DISK = 'public';

    /**
     * Display a listing of the restaurant's dishes, with everything the
     * create/edit dialog needs and whether the public menu is live.
     */
    public function index(Restaurant $restaurant): Response
    {
        Gate::authorize('manage', $restaurant);

        $imageDisk = Storage::disk(self::IMAGE_DISK);

        return Inertia::render('menu/dishes/index', [
            'restaurant' => $restaurant->only(['id', 'name_en', 'slug']),
            'verified' => (bool) $restaurant->verification?->verified,
            'kitchens' => $restaurant->kitchens()->get(['id', 'name_en', 'name_ar']),
            'categories' => $restaurant->categories()->get(['id', 'name_en', 'name_ar']),
            'imageLimits' => [
                'max_images' => SaveDishRequest::MAX_IMAGES,
                'max_image_bytes' => SaveDishRequest::MAX_IMAGE_KILOBYTES * 1024,
                'max_request_bytes' => UploadedFile::getMaxFilesize(),
            ],
            'dishes' => Dish::query()
                ->whereHas('kitchen', fn ($query) => $query->where('restaurant_id', $restaurant->id))
                ->with([
                    'kitchen:id,name_en',
                    'category:id,name_en',
                    'options:id,dish_id,name_en,name_ar,price',
                    'servingSizes:id,dish_id,name_en,name_ar,price,is_default,servings_count',
                ])
                ->orderBy('name_en')
                ->get()
                ->map(fn (Dish $dish) => [
                    ...$dish->only([
                        'id', 'kitchen_id', 'category_id', 'name_en', 'name_ar', 'description_en',
                        'description_ar', 'price', 'is_available', 'kitchen', 'category', 'options',
                    ]),
                    'images' => $dish->images ?? [],
                    'image_urls' => array_map(fn (string $path) => $imageDisk->url($path), $dish->images ?? []),
                    'serving_sizes' => $dish->servingSizes,
                ]),
        ]);
    }

    /**
     * Store a newly created dish with its images, options and serving sizes.
     */
    public function store(SaveDishRequest $request, Restaurant $restaurant): RedirectResponse
    {
        Gate::authorize('create', [Dish::class, $restaurant->kitchens()->findOrFail($request->validated('kitchen_id'))]);

        $images = $this->storeImages($request);

        DB::transaction(function () use ($request, $images) {
            $dish = Dish::create([...$request->dishAttributes(), 'images' => $images]);

            $dish->options()->createMany($request->validated('options', []));
            $dish->servingSizes()->createMany($request->validated('serving_sizes', []));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dish created.')]);

        return to_route('dishes.index', $restaurant);
    }

    /**
     * Update the specified dish, adding new images and deleting removed ones.
     */
    public function update(SaveDishRequest $request, Dish $dish): RedirectResponse
    {
        Gate::authorize('update', $dish);

        $removedImages = $request->validated('removed_images', []);
        $keptImages = array_values(array_diff($dish->images ?? [], $removedImages));

        $dish->update([
            ...$request->dishAttributes(),
            'images' => [...$keptImages, ...$this->storeImages($request)],
        ]);

        Storage::disk(self::IMAGE_DISK)->delete($removedImages);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dish updated.')]);

        return to_route('dishes.index', $dish->kitchen->restaurant);
    }

    /**
     * Store the request's uploaded images and return their disk paths.
     *
     * @return array<int, string>
     *
     * @throws RuntimeException when an image cannot be written to the disk.
     */
    private function storeImages(SaveDishRequest $request): array
    {
        return array_map(
            fn (UploadedFile $image): string => $image->store('dishes', self::IMAGE_DISK)
                ?: throw new RuntimeException('Failed to store dish image.'),
            $request->file('images', []),
        );
    }
}
