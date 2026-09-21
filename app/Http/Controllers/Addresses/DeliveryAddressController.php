<?php

namespace App\Http\Controllers\Addresses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Addresses\SaveDeliveryAddressRequest;
use App\Models\DeliveryAddress;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryAddressController extends Controller
{
    /**
     * Display the authenticated user's saved delivery addresses.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('addresses/index', [
            'addresses' => $request->user()
                ->deliveryAddresses()
                ->orderByDesc('is_default')
                ->orderBy('caption')
                ->get(['id', 'caption', 'address', 'lat', 'lng', 'is_default']),
        ]);
    }

    /**
     * Show the form for creating a new delivery address.
     */
    public function create(): Response
    {
        Gate::authorize('create', DeliveryAddress::class);

        return Inertia::render('addresses/create');
    }

    /**
     * Store a newly created delivery address for the authenticated user.
     */
    public function store(SaveDeliveryAddressRequest $request): RedirectResponse
    {
        Gate::authorize('create', DeliveryAddress::class);

        $address = DB::transaction(function () use ($request): DeliveryAddress {
            $address = $request->user()->deliveryAddresses()->create($this->validatedAddress($request));

            $this->unsetOtherDefaults($request->user(), $address);

            return $address;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address saved.')]);

        return to_route('addresses.index');
    }

    /**
     * Show the form for editing a delivery address.
     */
    public function edit(DeliveryAddress $address): Response
    {
        Gate::authorize('update', $address);

        return Inertia::render('addresses/edit', [
            'deliveryAddress' => $address->only(['id', 'caption', 'address', 'lat', 'lng', 'is_default']),
        ]);
    }

    /**
     * Update the specified delivery address.
     */
    public function update(SaveDeliveryAddressRequest $request, DeliveryAddress $address): RedirectResponse
    {
        Gate::authorize('update', $address);

        DB::transaction(function () use ($request, $address): void {
            $address->update($this->validatedAddress($request));

            $this->unsetOtherDefaults($request->user(), $address);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address updated.')]);

        return to_route('addresses.index');
    }

    /**
     * Remove the specified delivery address.
     */
    public function destroy(DeliveryAddress $address): RedirectResponse
    {
        Gate::authorize('delete', $address);

        $address->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address deleted.')]);

        return to_route('addresses.index');
    }

    /**
     * The validated address attributes, with the default flag resolved
     * from the checkbox (unchecked submits nothing, meaning not default).
     *
     * @return array<string, mixed>
     */
    private function validatedAddress(SaveDeliveryAddressRequest $request): array
    {
        return [
            ...$request->safe()->except('is_default'),
            'is_default' => $request->boolean('is_default'),
        ];
    }

    /**
     * Ensure the given address is the user's only default, if it is one.
     */
    private function unsetOtherDefaults(User $user, DeliveryAddress $address): void
    {
        if (! $address->is_default) {
            return;
        }

        $user->deliveryAddresses()
            ->whereKeyNot($address->id)
            ->update(['is_default' => false]);
    }
}
