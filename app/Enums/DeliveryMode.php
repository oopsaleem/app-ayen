<?php

namespace App\Enums;

enum DeliveryMode: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';

    /**
     * The order status the order takes once every dish is ready.
     */
    public function toOrderStatus(): OrderStatus
    {
        return match ($this) {
            self::Delivery => OrderStatus::AwaitingDelivery,
            self::Pickup => OrderStatus::AwaitingPickup,
        };
    }
}
