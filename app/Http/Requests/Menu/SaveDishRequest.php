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
