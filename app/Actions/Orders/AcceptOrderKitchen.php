<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderKitchen;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A chef accepts, on behalf of their kitchen, all of an order's dishes
 * belonging to that kitchen (ADR-0010). The first chef of the kitchen to act
 * wins; once every OrderKitchen row for the order is accepted, the order
 * auto-transitions PENDING → CONFIRMED.
 */
class AcceptOrderKitchen
{
    /**
     * Accept the order's dishes for the acting chef's kitchen.
     *
     * @return bool true when this call performed the acceptance; false when
     *              the rows were already accepted (second chef of the same
     *              kitchen, or the order was cancelled) — a deliberate no-op.
     */
    public function handle(OrderKitchen $orderKitchen, User $chefUser): bool
    {
        return DB::transaction(function () use ($orderKitchen, $chefUser): bool {
            $locked = OrderKitchen::query()
                ->whereKey($orderKitchen->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->accepted_at !== null) {
                return false;
            }

            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            if ($order->status !== OrderStatus::Pending) {
                return false;
            }

            $locked->forceFill([
                'accepted_at' => now(),
                'accepted_by' => $chefUser->getKey(),
            ])->save();

            $outstanding = OrderKitchen::query()
                ->where('order_id', $order->getKey())
                ->whereNull('accepted_at')
                ->exists();

            if (! $outstanding) {
                $order->transitionTo(OrderStatus::Confirmed);
            }

            return true;
        });
    }
}
