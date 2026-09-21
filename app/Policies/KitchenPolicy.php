<?php

namespace App\Policies;

use App\Models\Kitchen;
use App\Models\Restaurant;
use App\Models\User;

class KitchenPolicy
{
    /**
     * Determine whether the user can view the kitchen.
     */
    public function view(User $user, Kitchen $kitchen): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $kitchen->restaurant->company_id;
    }

    /**
     * Determine whether the user can create a kitchen under the given restaurant.
     */
    public function create(User $user, Restaurant $restaurant): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $restaurant->company_id;
    }

    /**
     * Determine whether the user can update the kitchen.
     */
    public function update(User $user, Kitchen $kitchen): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $kitchen->restaurant->company_id;
    }

    /**
     * Determine whether the user can delete the kitchen.
     */
    public function delete(User $user, Kitchen $kitchen): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $kitchen->restaurant->company_id;
    }
}
