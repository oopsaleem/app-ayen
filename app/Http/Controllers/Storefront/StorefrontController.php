<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Support\MenuTree;
use Illuminate\Support\Facades\Storage;
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

        $imageDisk = Storage::disk('public');

        return Inertia::render('storefront/show', [
            'restaurant' => [
                ...$restaurant->only(['id', 'name_en', 'description_en']),
                'image_urls' => array_map(fn (string $path) => $imageDisk->url($path), $restaurant->images ?? []),
            ],
            'categories' => MenuTree::forRestaurant($restaurant),
        ]);
    }
}
