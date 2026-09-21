<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\Restaurant;
use App\Models\User;

class RestaurantPolicy
{
    /**
     * Determine whether the user can view the restaurant.
     *
     * Phase 1 does not gate browsing on verification status — an
     * unverified restaurant is just as viewable as a verified one.
     */
    public function view(User $user, Restaurant $restaurant): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create a restaurant under the given company.
     */
    public function create(User $user, Company $company): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $company->id;
    }

    /**
     * Determine whether the user can update the restaurant.
     */
    public function update(User $user, Restaurant $restaurant): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $restaurant->company_id;
    }

    /**
     * Determine whether the user can delete the restaurant.
     */
    public function delete(User $user, Restaurant $restaurant): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $restaurant->company_id;
    }

    /**
     * Determine whether the user can verify the restaurant.
     */
    public function verify(User $user, Restaurant $restaurant): bool
    {
        return $user->isAdmin();
    }
}
