<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\CreateOrder;
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

class OrderController extends Controller
{
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
