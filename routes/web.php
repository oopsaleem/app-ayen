<?php

use App\Http\Controllers\Addresses\DeliveryAddressController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Orders\OrderController;
use App\Http\Controllers\Setup\AdminSetupController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureAdminSetupAccessible;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(EnsureAdminSetupAccessible::class)->group(function () {
    Route::get('setup/admin', [AdminSetupController::class, 'create'])->name('setup.admin.create');
    Route::post('setup/admin', [AdminSetupController::class, 'store'])->name('setup.admin.store');
});

Route::post('/locale', [LocaleController::class, 'update'])
    ->middleware(ThrottleRequests::class.':10,1')
    ->name('locale.update');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::get('restaurants/{restaurant}/order', [OrderController::class, 'create'])->name('restaurants.order');
    });

Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::get('addresses', [DeliveryAddressController::class, 'index'])->name('addresses.index');
        Route::post('addresses', [DeliveryAddressController::class, 'store'])->name('addresses.store');
        Route::patch('addresses/{address}', [DeliveryAddressController::class, 'update'])->name('addresses.update');
        Route::delete('addresses/{address}', [DeliveryAddressController::class, 'destroy'])->name('addresses.destroy');
    });

require __DIR__.'/settings.php';

require __DIR__.'/restaurants.php';

require __DIR__.'/storefront.php';
