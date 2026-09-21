<?php

namespace App\Http\Requests\Menu;

use App\Models\Category;
use App\Models\Restaurant;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $routeRestaurant = $this->route('restaurant');
        $routeCategory = $this->route('category');

        $restaurant = match (true) {
            $routeRestaurant instanceof Restaurant => $routeRestaurant,
            $routeCategory instanceof Category => $routeCategory->restaurant,
            default => null,
        };

        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('restaurant_id', $restaurant?->id),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $category = $this->route('category');

                    if ($category instanceof Category && $this->isOwnAncestor($category, (int) $value)) {
                        $fail('A category cannot be moved under itself or one of its descendants.');
                    }
                },
            ],
        ];
    }

    /**
     * Determine whether the candidate parent is the category itself or
     * one of its descendants. Walking up from the candidate reaches the
     * category being moved exactly when the candidate sits inside its
     * subtree, which mirrors Category::guardAgainstCycle().
     */
    protected function isOwnAncestor(Category $category, int $candidateParentId): bool
    {
        $ancestorId = $candidateParentId;
        $visited = [];

        while ($ancestorId !== 0) {
            if ($ancestorId === $category->id) {
                return true;
            }

            if (in_array($ancestorId, $visited, true)) {
                break;
            }

            $visited[] = $ancestorId;

            $nextAncestorId = Category::query()->whereKey($ancestorId)->value('parent_id');
            $ancestorId = $nextAncestorId !== null ? (int) $nextAncestorId : 0;
        }

        return false;
    }
}
