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
