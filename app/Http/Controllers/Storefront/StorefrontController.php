<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\Restaurant;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    /**
     * Render the public menu for a verified restaurant, so a visitor can
     * browse and build a cart without an account.
     */
    public function show(Restaurant $restaurant): Response
    {
        abort_unless($restaurant->verification?->verified, 404);

        $dishes = Dish::query()
            ->whereHas('kitchen', fn ($query) => $query->where('restaurant_id', $restaurant->id))
            ->where('is_available', true)
            ->with([
                'servingSizes' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name_en'),
                'options' => fn ($query) => $query->where('is_available', true)->orderBy('name_en'),
            ])
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ar', 'description_en', 'price']);

        return Inertia::render('storefront/show', [
            'restaurant' => $restaurant->only(['id', 'name_en', 'description_en']),
            'dishes' => $dishes,
        ]);
    }
}
