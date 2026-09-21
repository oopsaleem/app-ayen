<?php

namespace App\Http\Controllers\Rider;

use App\Enums\DeliveryMode;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Rider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class RiderOrderController extends Controller
{
    /**
     * The statuses the rider can see and act on (ADR-0012: open pool).
     */
    private const ActionableStatuses = [
        OrderStatus::AwaitingDelivery,
        OrderStatus::OutForDelivery,
    ];

    /**
     * Display the actionable delivery orders of the rider's restaurant.
     */
    public function index(Request $request): Response
    {
        $restaurantId = $this->authorizeRider($request)->restaurant_id;

        $orders = Order::query()
            ->where('restaurant_id', $restaurantId)
            ->where('delivery_mode', DeliveryMode::Delivery->value)
            ->whereIn('status', self::ActionableStatuses)
            ->with('restaurant:id,name_en')
            ->latest()
            ->get()
            ->map(
                /**
                 * @return array<string, mixed>
                 */
                fn (Order $order): array => [
                    'id' => $order->id,
                    'status' => $order->status->value,
                    'total' => $order->total,
                    'created_at' => $order->created_at?->toISOString(),
                    'restaurant' => ['name_en' => $order->restaurant->name_en],
                ],
            );

        return Inertia::render('rider/orders', [
            'orders' => $orders,
        ]);
    }

    /**
     * Pick up the order for delivery: AWAITING_DELIVERY → OUT_FOR_DELIVERY.
     */
    public function pickup(Request $request): RedirectResponse
    {
        return $this->transition($request, OrderStatus::OutForDelivery);
    }

    /**
     * Mark the order as delivered: OUT_FOR_DELIVERY → CLOSED.
     */
    public function deliver(Request $request): RedirectResponse
    {
        return $this->transition($request, OrderStatus::Closed);
    }

    /**
     * Move the requested order to the target status, when it is actionable
     * for the acting rider's restaurant (open pool, first to act wins).
     */
    private function transition(Request $request, OrderStatus $target): RedirectResponse
    {
        $order = Order::query()->findOrFail((int) $request->route('order'));

        $rider = $this->authorizeRider($request);

        abort_unless($rider->restaurant_id === $order->restaurant_id, 403);

        try {
            DB::transaction(function () use ($order, $target): void {
                $locked = Order::query()
                    ->whereKey($order->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $locked->transitionTo($target);
            });
        } catch (LogicException $e) {
            abort(422, $e->getMessage());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Order status updated.'),
        ]);

        return back();
    }

    /**
     * The acting user's Rider role; aborts when they hold none.
     */
    private function authorizeRider(Request $request): Rider
    {
        $rider = $request->user()->rider;

        abort_unless($rider !== null, 403);

        return $rider;
    }
}
