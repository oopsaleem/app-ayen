<?php

namespace App\Policies;

use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can create orders.
     *
     * Any authenticated user can be a customer, so placing an order is
     * not restricted by other roles the user may hold.
     */
    public function create(User $user): bool
    {
        return true;
    }
}
