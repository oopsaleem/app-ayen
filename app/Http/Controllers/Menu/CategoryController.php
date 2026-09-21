<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\SaveCategoryRequest;
use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of the restaurant's categories.
     */
    public function index(Restaurant $restaurant): Response
    {
        Gate::authorize('view', $restaurant);

        return Inertia::render('menu/categories/index', [
            'restaurant' => $restaurant->only(['id', 'name_en']),
            'categories' => $restaurant->categories()
                ->orderBy('level')->orderBy('name_en')
                ->get(['id', 'parent_id', 'name_en', 'name_ar', 'level']),
        ]);
    }

    /**
     * Show the form for creating a category under the given restaurant.
     */
    public function create(Restaurant $restaurant): Response
    {
        Gate::authorize('create', [Category::class, $restaurant]);

        return Inertia::render('menu/categories/create', [
            'restaurant' => $restaurant->only(['id', 'name_en']),
            'parents' => $restaurant->categories()->get(['id', 'name_en']),
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(SaveCategoryRequest $request, Restaurant $restaurant): RedirectResponse
    {
        Gate::authorize('create', [Category::class, $restaurant]);

        $restaurant->categories()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('categories.index', $restaurant);
    }

    /**
     * Show the form for editing a category.
     */
    public function edit(Category $category): Response
    {
        Gate::authorize('view', $category);

        return Inertia::render('menu/categories/edit', [
            'category' => $category->only(['id', 'restaurant_id', 'parent_id', 'name_en', 'name_ar']),
            'parents' => $category->restaurant->categories()
                ->whereKeyNot($category->id)
                ->get(['id', 'name_en']),
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('categories.index', $category->restaurant);
    }
}
