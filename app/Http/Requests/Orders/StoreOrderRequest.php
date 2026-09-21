<?php

namespace App\Http\Requests\Orders;

use App\Enums\DeliveryMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Validation rules for placing an order. Only selections are accepted —
     * there are no price-shaped fields to validate against (ADR-0007), so any
     * price sent by a client is simply ignored.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delivery_mode' => ['required', Rule::enum(DeliveryMode::class)],
            'delivery_address_id' => [
                'nullable',
                'required_if:delivery_mode,delivery',
                'prohibited_if:delivery_mode,pickup',
                Rule::exists('delivery_addresses', 'id')->where('user_id', $this->user()->id),
            ],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.dish_id' => ['required', 'integer', Rule::exists('dishes', 'id')],
            'lines.*.serving_size_id' => ['nullable', 'integer', Rule::exists('serving_sizes', 'id')],
            'lines.*.option_ids' => ['nullable', 'array'],
            'lines.*.option_ids.*' => ['integer', Rule::exists('dish_options', 'id')],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
