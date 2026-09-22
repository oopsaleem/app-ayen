<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\CreateOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class OrderController extends Controller
{
    /**
     * List the orders visible to the authenticated user: an Admin sees
     * every order, a Manager sees their company's restaurants' orders,
     * and everyone else sees only their own orders as a Customer.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $orders = Order::query()
            ->when(
                ! $user->isAdmin(),
                fn ($query) => $user->manager !== null
                    ? $query->whereHas('restaurant', fn ($q) => $q->where('company_id', $user->manager->company_id))
                    : $query->where('user_id', $user->id),
            )
            ->with('restaurant:id,name_en,company_id')
            ->latest()
            ->get()
            ->map(
                /**
                 * @return array<string, mixed>
                 */
                fn (Order $order): array => [
                    'id' => $order->id,
                    'status' => $order->status->value,
                    'delivery_mode' => $order->delivery_mode->value,
                    'total' => $order->total,
                    'created_at' => $order->created_at?->toISOString(),
                    'restaurant' => ['name_en' => $order->restaurant->name_en],
                    'can_cancel' => $user->can('cancel', $order),
                ],
            );

        return Inertia::render('orders/index', [
            'orders' => $orders,
        ]);
    }

    /**
     * Place a new order for the authenticated customer.
     */
    public function store(StoreOrderRequest $request, CreateOrder $createOrder): RedirectResponse
    {
        Gate::authorize('create', Order::class);

        $order = $createOrder->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order placed.')]);

        return to_route('restaurants.order', $order->restaurant);
    }

    /**
     * Cancel the order (ADR-0011): the acting user's role and the order's
     * current status determine whether this is allowed.
     */
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        try {
            $order->transitionTo(OrderStatus::Cancelled);
        } catch (LogicException $e) {
            abort(422, $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order cancelled.')]);

        return back();
    }

    /**
     * Render the order-placement page for a restaurant's menu.
     */
    public function create(Request $request, Restaurant $restaurant): Response
    {
        Gate::authorize('create', Order::class);

        $dishes = Dish::query()
            ->whereHas('kitchen', fn ($query) => $query->where('restaurant_id', $restaurant->id))
            ->where('is_available', true)
            ->with([
                'servingSizes' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name_en'),
                'options' => fn ($query) => $query->where('is_available', true)->orderBy('name_en'),
            ])
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ar', 'description_en', 'price']);

        return Inertia::render('restaurants/order', [
            'restaurant' => $restaurant->only(['id', 'name_en']),
            'dishes' => $dishes,
            'addresses' => $request->user()->deliveryAddresses()
                ->orderByDesc('is_default')
                ->orderBy('caption')
                ->get(['id', 'caption', 'address', 'is_default']),
        ]);
    }
}
