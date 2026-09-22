<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
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

    /**
     * Determine whether the user can cancel the order (ADR-0011): a Customer
     * may cancel only their own order while PENDING; a Manager of the
     * order's restaurant's company may cancel while PENDING, CONFIRMED, or
     * PREPARING; an Admin may cancel from any of those same statuses,
     * unrestricted by ownership. No role may cancel once the order has
     * moved past PREPARING (ADR-0010 does not make CANCELLED reachable from
     * a later status for anyone).
     */
    public function cancel(User $user, Order $order): bool
    {
        $cancellable = [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Preparing];

        if (! in_array($order->status, $cancellable, true)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->manager?->company_id === $order->restaurant->company_id) {
            return true;
        }

        return $order->status === OrderStatus::Pending && $order->user_id === $user->id;
    }
}
