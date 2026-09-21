<?php

namespace App\Http\Controllers\Waiter;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Waiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class WaiterOrderController extends Controller
{
    /**
     * Display the actionable pickup orders of the waiter's restaurant.
     */
    public function index(Request $request): Response
    {
        $restaurantId = $this->authorizeWaiter($request)->restaurant_id;

        $orders = Order::query()
            ->where('restaurant_id', $restaurantId)
            ->where('delivery_mode', 'pickup')
            ->where('status', OrderStatus::AwaitingPickup)
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

        return Inertia::render('waiter/orders', [
            'orders' => $orders,
        ]);
    }

    /**
     * Hand off the order to the customer: AWAITING_PICKUP → CLOSED
     * (ADR-0010). Any waiter scoped to the order's restaurant may act —
     * first to act wins, no assignment record (ADR-0012).
     */
    public function handOff(Request $request): RedirectResponse
    {
        $order = Order::query()->findOrFail((int) $request->route('order'));

        $waiter = $this->authorizeWaiter($request);

        abort_unless($waiter->restaurant_id === $order->restaurant_id, 403);

        try {
            DB::transaction(function () use ($order): void {
                $locked = Order::query()
                    ->whereKey($order->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $locked->transitionTo(OrderStatus::Closed);
            });
        } catch (LogicException $e) {
            abort(422, $e->getMessage());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Order handed off to the customer.'),
        ]);

        return back();
    }

    /**
     * The acting user's Waiter role; aborts when they hold none.
     */
    private function authorizeWaiter(Request $request): Waiter
    {
        $waiter = $request->user()->waiter;

        abort_unless($waiter !== null, 403);

        return $waiter;
    }
}
