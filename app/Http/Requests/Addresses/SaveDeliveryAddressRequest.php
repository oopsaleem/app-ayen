<?php

namespace App\Http\Requests\Addresses;

use Illuminate\Foundation\Http\FormRequest;

class SaveDeliveryAddressRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'caption' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
