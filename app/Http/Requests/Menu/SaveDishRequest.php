<?php

namespace App\Http\Requests\Menu;

use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDishRequest extends FormRequest
{
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
