<?php

namespace App\Enums;

enum OrderDishStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Ready = 'ready';

    /**
     * The statuses each dish status may legally transition to (ADR-0010).
     *
     * @var array<string, list<string>>
     */
    private const Transitions = [
        'pending' => ['preparing'],
        'preparing' => ['ready'],
    ];

    /**
     * Whether a transition from this status to the given status is legal.
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to->value, self::Transitions[$this->value] ?? [], true);
    }
}
