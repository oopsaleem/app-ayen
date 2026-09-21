<?php

namespace App\Policies;

use App\Models\DeliveryAddress;
use App\Models\User;

class DeliveryAddressPolicy
{
    /**
     * Determine whether the user can create delivery addresses.
     *
     * Any authenticated user can be a customer, so creating a saved
     * delivery address is not restricted by other roles.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the delivery address.
     *
     * Delivery addresses are own-your-data: a customer only ever
     * manages their own saved addresses.
     */
    public function update(User $user, DeliveryAddress $address): bool
    {
        return $user->id === $address->user_id;
    }

    /**
     * Determine whether the user can delete the delivery address.
     *
     * Delivery addresses are own-your-data: a customer only ever
     * manages their own saved addresses.
     */
    public function delete(User $user, DeliveryAddress $address): bool
    {
        return $user->id === $address->user_id;
    }
}
