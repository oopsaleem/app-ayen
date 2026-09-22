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
     * Determine whether the user can request any order list at all. Every
     * authenticated user sees some scoped list: Chef/Rider/Waiter have
     * their own action-scoped dashboards, while the generic orders list
     * scopes Admin (everything), Manager (their company's restaurants'
     * orders) and everyone else (only orders they placed).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the order, per the scoping rule
     * that applies to the actor (ADR-0012): an Admin sees everything; a
     * Manager sees their company's restaurants' orders; a Chef sees orders
     * containing dishes from their kitchens; a Rider sees delivery orders
     * of their restaurant; a Waiter sees orders of their restaurant; anyone
     * else sees only orders they placed themselves.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $restaurantId = $order->restaurant_id;

        $kitchenIds = $user->chef?->kitchens()->pluck('kitchens.id');

        if ($kitchenIds !== null && $kitchenIds->isNotEmpty()
            && (
                $order->dishes()
                    ->whereHas('dish', fn ($query) => $query->whereIn('kitchen_id', $kitchenIds))
                    ->exists()
                || $order->kitchens()
                    ->whereIn('order_kitchens.kitchen_id', $kitchenIds)
                    ->exists()
            )
        ) {
            return true;
        }

        if ($user->rider !== null
            && $user->rider->restaurant_id === $restaurantId
            && $order->delivery_mode->isDelivery()
        ) {
            return true;
        }

        if ($user->waiter !== null && $user->waiter->restaurant_id === $restaurantId) {
            return true;
        }

        if ($user->manager !== null
            && $user->manager->company_id === $order->restaurant->company_id
        ) {
            return true;
        }

        return $order->user_id === $user->id;
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
