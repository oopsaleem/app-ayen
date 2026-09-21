<?php

namespace App\Http\Controllers\Chef;

use App\Actions\Orders\AcceptOrderKitchen;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Chef;
use App\Models\Order;
use App\Models\OrderKitchen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ChefOrderController extends Controller
{
    /**
     * Display the orders containing dishes from the chef's assigned kitchens.
     */
    public function index(Request $request): Response
    {
        $kitchenIds = $this->kitchenIdsFor($request);

        $orders = Order::query()
            ->whereHas('kitchens', fn ($query) => $query->whereIn('order_kitchens.kitchen_id', $kitchenIds))
            ->whereIn('status', [OrderStatus::Pending, OrderStatus::Confirmed])
            ->with(['restaurant', 'kitchens.kitchen', 'dishes.dish'])
            ->latest()
            ->get()
            ->map(fn ($order) => $this->presentOrder($order, $kitchenIds));

        return Inertia::render('chef/orders', [
            'orders' => $orders,
        ]);
    }

    /**
     * Accept an order on behalf of the acting chef's kitchen.
     */
    public function accept(Request $request, AcceptOrderKitchen $acceptOrderKitchen): RedirectResponse
    {
        $orderKitchen = OrderKitchen::query()
            ->where('order_id', (int) $request->route('order'))
            ->where('kitchen_id', (int) $request->route('kitchen'))
            ->firstOrFail();

        $chef = $this->authorizeChef($request);

        abort_unless($chef->kitchens()->whereKey($orderKitchen->kitchen_id)->exists(), 403);

        $accepted = $acceptOrderKitchen->handle($orderKitchen, $request->user());

        $orderKitchen->refresh();

        $message = match (true) {
            $accepted => __('Order accepted for this kitchen.'),
            $orderKitchen->accepted_at !== null => __('This kitchen already accepted the order.'),
            default => __('The order cannot be accepted.'),
        };

        Inertia::flash('toast', [
            'type' => $accepted ? 'success' : 'info',
            'message' => $message,
        ]);

        return back();
    }

    /**
     * The acting user's Chef role; aborts when they hold none.
     */
    private function authorizeChef(Request $request): Chef
    {
        $chef = $request->user()->chef;

        abort_unless($chef !== null, 403);

        return $chef;
    }

    /**
     * The ids of the kitchens the authenticated chef is assigned to.
     *
     * @return Collection<int, int>
     */
    private function kitchenIdsFor(Request $request): Collection
    {
        $chef = $this->authorizeChef($request);

        return $chef->kitchens()->pluck('kitchens.id');
    }

    /**
     * Shape an order for the chef-facing page, restricted to the chef's
     * kitchens and the order lines belonging to them.
     *
     * @param  Collection<int, int>  $kitchenIds
     * @return array<string, mixed>
     */
    private function presentOrder(Order $order, Collection $kitchenIds): array
    {
        $kitchens = $order->kitchens
            ->filter(fn ($orderKitchen) => $kitchenIds->contains($orderKitchen->kitchen_id))
            ->map(fn ($orderKitchen) => [
                'id' => $orderKitchen->id,
                'kitchen_id' => $orderKitchen->kitchen_id,
                'name_en' => $orderKitchen->kitchen->name_en,
                'name_ar' => $orderKitchen->kitchen->name_ar,
                'accepted' => $orderKitchen->accepted_at !== null,
                'dishes' => $order->dishes
                    ->where('dish.kitchen_id', $orderKitchen->kitchen_id)
                    ->map(fn ($orderDish) => [
                        'name_en' => $orderDish->dish->name_en,
                        'quantity' => $orderDish->quantity,
                    ])->values(),
            ])->values();

        return [
            'id' => $order->id,
            'status' => $order->status->value,
            'delivery_mode' => $order->delivery_mode->value,
            'total' => $order->total,
            'created_at' => $order->created_at?->toISOString(),
            'restaurant' => ['name_en' => $order->restaurant->name_en],
            'kitchens' => $kitchens,
        ];
    }
}
