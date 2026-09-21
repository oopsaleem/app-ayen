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
     * The statuses each status may legally transition to (ADR-0010).
     *
     * @var array<string, list<string>>
     */
    private const Transitions = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['awaiting_delivery', 'awaiting_pickup', 'cancelled'],
        'awaiting_delivery' => ['out_for_delivery'],
        'out_for_delivery' => ['closed'],
        'awaiting_pickup' => ['closed'],
    ];

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return __(ucwords(str_replace('_', ' ', $this->value)));
    }

    /**
     * Whether a transition from this status to the given status is legal.
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to->value, self::Transitions[$this->value] ?? [], true);
    }
}
