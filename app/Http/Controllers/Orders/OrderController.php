<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\CreateOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderDish;
use App\Models\OrderStatusHistory;
use App\Models\Restaurant;
use App\Support\MenuTree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
        Gate::authorize('viewAny', Order::class);

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
     * Render the order detail page: the dish lines and the full status
     * history timeline for a user permitted to view the order.
     */
    public function show(Request $request, Order $order): Response
    {
        Gate::authorize('view', $order);

        $order->load(['restaurant:id,name_en', 'dishes.dish:id,name_en,kitchen_id']);

        return Inertia::render('orders/show', [
            'order' => [
                'id' => $order->id,
                'status' => $order->status->value,
                'delivery_mode' => $order->delivery_mode->value,
                'total' => $order->total,
                'created_at' => $order->created_at?->toISOString(),
                'restaurant' => ['name_en' => $order->restaurant->name_en],
                'can_cancel' => $request->user()->can('cancel', $order),
                'dishes' => $order->dishes->map(
                    /**
                     * @return array<string, mixed>
                     */
                    fn (OrderDish $dish): array => [
                        'id' => $dish->id,
                        'name_en' => $dish->dish->name_en,
                        'quantity' => $dish->quantity,
                        'total_price' => $dish->total_price,
                    ],
                ),
                'status_history' => $order->statusHistory()
                    ->oldest('id')
                    ->get()
                    ->map(
                        /**
                         * @return array<string, mixed>
                         */
                        fn (OrderStatusHistory $row): array => [
                            'status' => $row->status->value,
                            'previous_status' => $row->previous_status?->value,
                            'duration_in_previous_status' => $row->duration_in_previous_status,
                            'created_at' => $row->created_at?->toISOString(),
                        ],
                    ),
            ],
        ]);
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

        $imageDisk = Storage::disk('public');

        return Inertia::render('restaurants/order', [
            'restaurant' => [
                ...$restaurant->only(['id', 'name_en', 'description_en']),
                'image_urls' => array_map(fn (string $path) => $imageDisk->url($path), $restaurant->images ?? []),
            ],
            'categories' => MenuTree::forRestaurant($restaurant),
            'addresses' => $request->user()->deliveryAddresses()
                ->orderByDesc('is_default')
                ->orderBy('caption')
                ->get(['id', 'caption', 'address', 'is_default']),
        ]);
    }
}
