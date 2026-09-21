<?php

namespace App\Http\Controllers\Restaurants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurants\SaveRestaurantRequest;
use App\Models\Company;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantController extends Controller
{
    /**
     * Display a listing of restaurants visible to the current user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $restaurants = Restaurant::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('company_id', $user->manager?->company_id))
            ->with('address')
            ->orderBy('name_en')
            ->get(['id', 'company_id', 'name_en', 'name_ar']);

        return Inertia::render('restaurants/index', [
            'restaurants' => $restaurants,
        ]);
    }

    /**
     * Show the form for creating a restaurant under the given company.
     */
    public function create(Company $company): Response
    {
        Gate::authorize('create', [Restaurant::class, $company]);

        return Inertia::render('restaurants/create', [
            'company' => $company->only(['id', 'display_name']),
        ]);
    }

    /**
     * Store a newly created restaurant.
     */
    public function store(SaveRestaurantRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', [Restaurant::class, $company]);

        $restaurant = DB::transaction(function () use ($request, $company) {
            $restaurant = Restaurant::create([
                'company_id' => $company->id,
                ...$request->safe()->except('address'),
            ]);

            $restaurant->address()->create($request->validated('address'));

            return $restaurant;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Restaurant created.')]);

        return to_route('restaurants.edit', $restaurant);
    }

    /**
     * Show the form for editing a restaurant.
     */
    public function edit(Restaurant $restaurant): Response
    {
        Gate::authorize('manage', $restaurant);

        $restaurant->load('address', 'verification', 'kitchens');

        return Inertia::render('restaurants/edit', [
            'restaurant' => $restaurant->only(['id', 'company_id', 'name_en', 'name_ar', 'description_en', 'description_ar', 'images']),
            'address' => $restaurant->address?->only(['address', 'lat', 'lng']),
            'kitchens' => $restaurant->kitchens->map->only(['id', 'name_en', 'name_ar']),
            'verified' => (bool) $restaurant->verification?->verified,
            'canVerify' => Gate::allows('verify', $restaurant),
        ]);
    }

    /**
     * Update the specified restaurant and its address.
     */
    public function update(SaveRestaurantRequest $request, Restaurant $restaurant): RedirectResponse
    {
        Gate::authorize('update', $restaurant);

        DB::transaction(function () use ($request, $restaurant) {
            $restaurant->update($request->safe()->except('address'));

            $restaurant->address()->updateOrCreate([], $request->validated('address'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Restaurant updated.')]);

        return to_route('restaurants.edit', $restaurant);
    }
}
