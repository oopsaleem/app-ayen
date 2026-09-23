<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryMode;
use App\Enums\OrderStatus;
use App\Models\Chef;
use App\Models\Company;
use App\Models\Manager;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Rider;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Models\Waiter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $email = strtolower($user->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'admin' => $user->isAdmin() ? $this->adminSummary() : null,
            'manager' => $user->manager ? $this->managerSummary($user->manager) : null,
            'chef' => $user->chef ? $this->chefSummary($user->chef) : null,
            'waiter' => $user->waiter ? $this->waiterSummary($user->waiter) : null,
            'rider' => $user->rider ? $this->riderSummary($user->rider) : null,
            'customer' => $this->customerSummary($user),
        ]);
    }

    /**
     * @return array{companies: int, restaurants: int, unverified_restaurants: int}
     */
    private function adminSummary(): array
    {
        return [
            'companies' => Company::count(),
            'restaurants' => Restaurant::count(),
            'unverified_restaurants' => Restaurant::query()
                ->whereDoesntHave('verification', fn ($query) => $query->where('verified', true))
                ->count(),
        ];
    }

    /**
     * @return array{restaurants: int}
     */
    private function managerSummary(Manager $manager): array
    {
        return [
            'restaurants' => Restaurant::query()
                ->where('company_id', $manager->company_id)
                ->count(),
        ];
    }

    /**
     * @return array{kitchens: int, actionable_orders: int}
     */
    private function chefSummary(Chef $chef): array
    {
        $kitchenIds = $chef->kitchens()->pluck('kitchens.id');

        return [
            'kitchens' => $kitchenIds->count(),
            'actionable_orders' => Order::query()
                ->whereHas('kitchens', fn ($query) => $query->whereIn('order_kitchens.kitchen_id', $kitchenIds))
                ->whereIn('status', [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Preparing])
                ->count(),
        ];
    }

    /**
     * @return array{pickups: int}
     */
    private function waiterSummary(Waiter $waiter): array
    {
        return [
            'pickups' => Order::query()
                ->where('restaurant_id', $waiter->restaurant_id)
                ->where('delivery_mode', DeliveryMode::Pickup->value)
                ->where('status', OrderStatus::AwaitingPickup)
                ->count(),
        ];
    }

    /**
     * @return array{deliveries: int}
     */
    private function riderSummary(Rider $rider): array
    {
        return [
            'deliveries' => Order::query()
                ->where('restaurant_id', $rider->restaurant_id)
                ->where('delivery_mode', DeliveryMode::Delivery->value)
                ->whereIn('status', [OrderStatus::AwaitingDelivery, OrderStatus::OutForDelivery])
                ->count(),
        ];
    }

    /**
     * @return array{active_orders: int}
     */
    private function customerSummary(User $user): array
    {
        return [
            'active_orders' => Order::query()
                ->where('user_id', $user->id)
                ->whereNotIn('status', [OrderStatus::Closed, OrderStatus::Cancelled])
                ->count(),
        ];
    }
}
