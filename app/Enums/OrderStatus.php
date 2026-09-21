<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case AwaitingDelivery = 'awaiting_delivery';
    case AwaitingPickup = 'awaiting_pickup';
    case OutForDelivery = 'out_for_delivery';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return __(ucwords(str_replace('_', ' ', $this->value)));
    }
}
