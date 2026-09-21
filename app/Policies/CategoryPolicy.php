<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Restaurant;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view the category.
     */
    public function view(User $user, Category $category): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $category->restaurant->company_id;
    }

    /**
     * Determine whether the user can create a category under the given restaurant.
     */
    public function create(User $user, Restaurant $restaurant): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $restaurant->company_id;
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $category->restaurant->company_id;
    }

    /**
     * Determine whether the user can delete the category.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->isAdmin() || $user->manager?->company_id === $category->restaurant->company_id;
    }
}
