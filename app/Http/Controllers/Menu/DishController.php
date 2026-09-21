<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\SaveDishRequest;
use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DishController extends Controller
{
    /**
     * Display a listing of the restaurant's dishes.
     */
    public function index(Restaurant $restaurant): Response
    {
        Gate::authorize('view', $restaurant);

        return Inertia::render('menu/dishes/index', [
            'restaurant' => $restaurant->only(['id', 'name_en']),
            'dishes' => Dish::query()
                ->whereHas('kitchen', fn ($query) => $query->where('restaurant_id', $restaurant->id))
                ->with(['kitchen:id,name_en', 'category:id,name_en'])
                ->orderBy('name_en')
                ->get(['id', 'kitchen_id', 'category_id', 'name_en', 'name_ar', 'price']),
        ]);
    }

    /**
     * Show the form for creating a dish under the given restaurant.
     */
    public function create(Restaurant $restaurant): Response
    {
        Gate::authorize('view', $restaurant);

        return Inertia::render('menu/dishes/create', [
            'restaurant' => $restaurant->only(['id', 'name_en']),
            'kitchens' => $restaurant->kitchens()->get(['id', 'name_en']),
            'categories' => $restaurant->categories()->get(['id', 'name_en']),
        ]);
    }

    /**
     * Store a newly created dish.
     */
    public function store(SaveDishRequest $request, Restaurant $restaurant): RedirectResponse
    {
        Gate::authorize('create', [Dish::class, $restaurant->kitchens()->findOrFail($request->validated('kitchen_id'))]);

        Dish::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dish created.')]);

        return to_route('dishes.index', $restaurant);
    }

    /**
     * Show the form for editing a dish.
     */
    public function edit(Dish $dish): Response
    {
        Gate::authorize('view', $dish);

        $restaurant = $dish->kitchen->restaurant;

        return Inertia::render('menu/dishes/edit', [
            'dish' => $dish->only(['id', 'kitchen_id', 'category_id', 'name_en', 'name_ar', 'price']),
            'kitchens' => $restaurant->kitchens()->get(['id', 'name_en']),
            'categories' => $restaurant->categories()->get(['id', 'name_en']),
            'options' => $dish->options()->get(['id', 'name_en', 'name_ar', 'price']),
            'servingSizes' => $dish->servingSizes()->get(['id', 'name_en', 'name_ar', 'price', 'is_default']),
        ]);
    }

    /**
     * Update the specified dish.
     */
    public function update(SaveDishRequest $request, Dish $dish): RedirectResponse
    {
        Gate::authorize('update', $dish);

        $dish->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dish updated.')]);

        return to_route('dishes.index', $dish->kitchen->restaurant);
    }
}
