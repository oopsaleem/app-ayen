<?php

namespace App\Actions\Orders;

use App\Enums\OrderDishStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderDish;
use DomainException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * A chef marks one of the order's dishes preparing, then ready (ADR-0010).
 * A dish cannot move to PREPARING unless the parent order is already
 * CONFIRMED. The moment any one dish is marked preparing, the order
 * auto-advances CONFIRMED → PREPARING; once every dish of the order is
 * ready, the order auto-advances to AWAITING_DELIVERY or AWAITING_PICKUP
 * depending on its delivery mode.
 */
class UpdateOrderDishStatus
{
    /**
     * Move the given order dish to the new status and run any order-level
     * auto-transition it implies.
     *
     * @throws LogicException when the dish transition is illegal
     * @throws DomainException when the order cannot hold dish preparation
     */
    public function handle(OrderDish $orderDish, OrderDishStatus $next): OrderDish
    {
        return DB::transaction(function () use ($orderDish, $next): OrderDish {
            $locked = OrderDish::query()
                ->whereKey($orderDish->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->status->canTransitionTo($next)) {
                throw new LogicException(
                    'A dish cannot transition from '.$locked->status->value.' to '.$next->value.'.',
                );
            }

            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            $preparingEligible = in_array(
                $order->status,
                [OrderStatus::Confirmed, OrderStatus::Preparing],
                true,
            );

            if ($next === OrderDishStatus::Preparing && ! $preparingEligible) {
                throw new DomainException('Dishes cannot begin preparing before the order is confirmed.');
            }

            $locked->forceFill(['status' => $next])->save();

            if ($order->status === OrderStatus::Confirmed && $next === OrderDishStatus::Preparing) {
                $order->transitionTo(OrderStatus::Preparing);
            }

            if ($order->status === OrderStatus::Preparing && $this->allDishesReady($order)) {
                $order->transitionTo($order->delivery_mode->toOrderStatus());
            }

            return $locked->refresh();
        });
    }

    /**
     * Whether every dish line of the order is ready (orders always have dishes).
     */
    private function allDishesReady(Order $order): bool
    {
        return $order->dishes()
            ->where('status', '!=', OrderDishStatus::Ready->value)
            ->doesntExist();
    }
}
