<?php

namespace App\Http\Requests\Menu;

use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDishRequest extends FormRequest
{
    public const MAX_IMAGES = 5;

    public const MAX_IMAGE_KILOBYTES = 2048;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $routeRestaurant = $this->route('restaurant');
        $routeDish = $this->route('dish');

        $restaurant = match (true) {
            $routeRestaurant instanceof Restaurant => $routeRestaurant,
            $routeDish instanceof Dish => $routeDish->kitchen->restaurant,
            default => null,
        };

        $childRowId = $routeDish instanceof Dish
            ? fn (string $table): array => ['nullable', 'integer', Rule::exists($table, 'id')->where('dish_id', $routeDish->id)]
            : fn (string $table): array => ['prohibited'];

        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
            'kitchen_id' => [
                'required',
                Rule::exists('kitchens', 'id')->where('restaurant_id', $restaurant?->id),
            ],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where('restaurant_id', $restaurant?->id),
            ],
            'images' => ['sometimes', 'array', 'max:'.self::MAX_IMAGES],
            'images.*' => ['image', 'max:'.self::MAX_IMAGE_KILOBYTES],
            'removed_images' => ['sometimes', 'array'],
            'removed_images.*' => ['string', Rule::in($routeDish instanceof Dish ? ($routeDish->images ?? []) : [])],
            'options' => ['sometimes', 'array'],
            'options.*.id' => $childRowId('dish_options'),
            'options.*.name_en' => ['required', 'string', 'max:255'],
            'options.*.name_ar' => ['required', 'string', 'max:255'],
            'options.*.price' => ['required', 'numeric', 'min:0'],
            'serving_sizes' => ['sometimes', 'array'],
            'serving_sizes.*.id' => $childRowId('serving_sizes'),
            'serving_sizes.*.name_en' => ['required', 'string', 'max:255'],
            'serving_sizes.*.name_ar' => ['required', 'string', 'max:255'],
            'serving_sizes.*.price' => ['required', 'numeric', 'min:0'],
            'serving_sizes.*.is_default' => ['sometimes', 'boolean'],
            'serving_sizes.*.servings_count' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * The validated columns that belong on the dish row itself.
     *
     * @return array<string, mixed>
     */
    public function dishAttributes(): array
    {
        return $this->safe()->except(['images', 'removed_images', 'options', 'serving_sizes']);
    }
}
