<?php

namespace App\Http\Controllers\Restaurants;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class RestaurantVerificationController extends Controller
{
    /**
     * Toggle the restaurant's verification status.
     */
    public function __invoke(Request $request, Restaurant $restaurant): RedirectResponse
    {
        Gate::authorize('verify', $restaurant);

        $verification = $restaurant->verification()->firstOrNew();
        $verification->verified = ! $verification->verified;
        $verification->verified_by_admin_id = $verification->verified ? $request->user()->admin->id : null;
        $verification->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Verification updated.')]);

        return to_route('restaurants.edit', $restaurant);
    }
}
