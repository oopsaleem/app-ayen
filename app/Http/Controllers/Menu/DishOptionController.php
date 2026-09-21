<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\SaveDishOptionRequest;
use App\Models\Dish;
use App\Models\DishOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DishOptionController extends Controller
{
    /**
     * Store a newly created option under the given dish.
     */
    public function store(SaveDishOptionRequest $request, Dish $dish): RedirectResponse
    {
        Gate::authorize('update', $dish);

        $dish->options()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option added.')]);

        return to_route('dishes.edit', $dish);
    }

    /**
     * Update the specified option.
     */
    public function update(SaveDishOptionRequest $request, DishOption $dishOption): RedirectResponse
    {
        Gate::authorize('update', $dishOption->dish);

        $dishOption->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option updated.')]);

        return to_route('dishes.edit', $dishOption->dish);
    }

    /**
     * Remove the specified option.
     */
    public function destroy(DishOption $dishOption): RedirectResponse
    {
        Gate::authorize('update', $dishOption->dish);

        $dish = $dishOption->dish;
        $dishOption->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Option removed.')]);

        return to_route('dishes.edit', $dish);
    }
}
