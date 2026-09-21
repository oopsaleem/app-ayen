<?php

namespace App\Http\Controllers\Kitchens;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchens\SaveKitchenRequest;
use App\Models\Kitchen;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class KitchenController extends Controller
{
    /**
     * Store a newly created kitchen under the given restaurant.
     */
    public function store(SaveKitchenRequest $request, Restaurant $restaurant): RedirectResponse
    {
        Gate::authorize('create', [Kitchen::class, $restaurant]);

        $restaurant->kitchens()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kitchen created.')]);

        return to_route('restaurants.edit', $restaurant);
    }

    /**
     * Show the form for editing a kitchen.
     */
    public function edit(Kitchen $kitchen): Response
    {
        Gate::authorize('view', $kitchen);

        return Inertia::render('kitchens/edit', [
            'kitchen' => $kitchen->only(['id', 'restaurant_id', 'name_en', 'name_ar']),
        ]);
    }

    /**
     * Update the specified kitchen.
     */
    public function update(SaveKitchenRequest $request, Kitchen $kitchen): RedirectResponse
    {
        Gate::authorize('update', $kitchen);

        $kitchen->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kitchen updated.')]);

        return to_route('restaurants.edit', $kitchen->restaurant);
    }
}
