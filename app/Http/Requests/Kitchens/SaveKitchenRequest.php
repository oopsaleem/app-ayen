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
