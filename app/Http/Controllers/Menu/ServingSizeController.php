<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\SaveServingSizeRequest;
use App\Models\Dish;
use App\Models\ServingSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ServingSizeController extends Controller
{
    /**
     * Store a newly created serving size under the given dish.
     */
    public function store(SaveServingSizeRequest $request, Dish $dish): RedirectResponse
    {
        Gate::authorize('update', $dish);

        DB::transaction(function () use ($request, $dish) {
            if ($request->boolean('is_default')) {
                $dish->servingSizes()->update(['is_default' => false]);
            }

            $dish->servingSizes()->create($request->validated());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Serving size added.')]);

        return to_route('dishes.index', $dish->kitchen->restaurant);
    }

    /**
     * Update the specified serving size.
     */
    public function update(SaveServingSizeRequest $request, ServingSize $servingSize): RedirectResponse
    {
        Gate::authorize('update', $servingSize->dish);

        DB::transaction(function () use ($request, $servingSize) {
            if ($request->boolean('is_default')) {
                $servingSize->dish->servingSizes()->whereKeyNot($servingSize->id)->update(['is_default' => false]);
            }

            $servingSize->update($request->validated());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Serving size updated.')]);

        return to_route('dishes.index', $servingSize->dish->kitchen->restaurant);
    }

    /**
     * Remove the specified serving size.
     */
    public function destroy(ServingSize $servingSize): RedirectResponse
    {
        Gate::authorize('update', $servingSize->dish);

        $dish = $servingSize->dish;
        $servingSize->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Serving size removed.')]);

        return to_route('dishes.index', $dish->kitchen->restaurant);
    }
}
