<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class MenuTree
{
    /**
     * Build a restaurant's browsable menu: the tree of its active
     * categories, each carrying its available dishes (with resolved image
     * URLs, serving sizes, and options) and its nested subcategories. A
     * category with no dishes anywhere in its subtree is dropped, so the
     * menu never renders an empty section.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forRestaurant(Restaurant $restaurant): Collection
    {
        $categories = Category::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('name_en')
            ->get(['id', 'parent_id', 'name_en', 'name_ar']);

        $dishesByCategory = Dish::query()
            ->whereIn('category_id', $categories->pluck('id'))
            ->where('is_available', true)
            ->with([
                'servingSizes' => fn ($query) => $query
                    ->select(['id', 'dish_id', 'name_en', 'name_ar', 'price', 'is_default', 'servings_count'])
                    ->orderByDesc('is_default')
                    ->orderBy('name_en'),
                'options' => fn ($query) => $query
                    ->select(['id', 'dish_id', 'name_en', 'name_ar', 'price'])
                    ->where('is_available', true)
                    ->orderBy('name_en'),
            ])
            ->orderBy('name_en')
            ->get(['id', 'category_id', 'name_en', 'name_ar', 'description_en', 'description_ar', 'price', 'images'])
            ->groupBy('category_id')
            ->all();

        $childrenByParent = $categories
            ->filter(fn (Category $category) => $category->parent_id !== null)
            ->groupBy('parent_id')
            ->all();

        $imageDisk = Storage::disk('public');

        $tree = [];

        foreach ($categories as $category) {
            if ($category->parent_id !== null) {
                continue;
            }

            $node = self::buildNode($category, $childrenByParent, $dishesByCategory, $imageDisk);

            if ($node !== null) {
                $tree[] = $node;
            }
        }

        return new Collection($tree);
    }

    /**
     * Recursively build the menu node for a category. Returns null when
     * neither the category nor any descendant holds an available dish, so
     * empty subtrees are pruned from the menu.
     *
     * @param  array<int|string, EloquentCollection<int, Category>>  $childrenByParent
     * @param  array<int|string, EloquentCollection<int, Dish>>  $dishesByCategory
     * @return array<string, mixed>|null
     */
    private static function buildNode(
        Category $category,
        array $childrenByParent,
        array $dishesByCategory,
        FilesystemAdapter $imageDisk,
    ): ?array {
        $dishes = [];

        $categoryDishes = $dishesByCategory[$category->id] ?? null;

        if ($categoryDishes !== null) {
            foreach ($categoryDishes as $dish) {
                $dishes[] = [
                    'id' => $dish->id,
                    'name_en' => $dish->name_en,
                    'name_ar' => $dish->name_ar,
                    'description_en' => $dish->description_en,
                    'description_ar' => $dish->description_ar,
                    'price' => $dish->price,
                    'image_urls' => array_map(fn (string $path) => $imageDisk->url($path), $dish->images ?? []),
                    'serving_sizes' => $dish->servingSizes->all(),
                    'options' => $dish->options->all(),
                ];
            }
        }

        $children = [];

        $childCategories = $childrenByParent[$category->id] ?? null;

        if ($childCategories !== null) {
            foreach ($childCategories as $child) {
                $node = self::buildNode($child, $childrenByParent, $dishesByCategory, $imageDisk);

                if ($node !== null) {
                    $children[] = $node;
                }
            }
        }

        if ($dishes === [] && $children === []) {
            return null;
        }

        return [
            'id' => $category->id,
            'name_en' => $category->name_en,
            'name_ar' => $category->name_ar,
            'dishes' => $dishes,
            'children' => $children,
        ];
    }
}
