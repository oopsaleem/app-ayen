<?php

namespace App\Policies;

use App\Models\Dish;
use App\Models\Kitchen;
use App\Models\User;

class DishPolicy
{
    /**
     * Determine whether the user can view the dish.
     */
    public function view(User $user, Dish $dish): bool
    {
        return $user->isAdmin()
            || $user->manager?->company_id === $dish->kitchen->restaurant->company_id
            || $user->chef?->kitchens()->whereKey($dish->kitchen_id)->exists();
    }

    /**
     * Determine whether the user can create a dish in the given kitchen.
     */
    public function create(User $user, Kitchen $kitchen): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $kitchen->restaurant->company_id;
    }

    /**
     * Determine whether the user can update the dish.
     */
    public function update(User $user, Dish $dish): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $dish->kitchen->restaurant->company_id;
    }

    /**
     * Determine whether the user can delete the dish.
     */
    public function delete(User $user, Dish $dish): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $dish->kitchen->restaurant->company_id;
    }
}
