<?php

use App\Http\Controllers\Chef\ChefKitchenController;
use App\Http\Controllers\Chef\ChefOrderController;
use App\Http\Controllers\Companies\CompanyController;
use App\Http\Controllers\Kitchens\KitchenController;
use App\Http\Controllers\Menu\CategoryController;
use App\Http\Controllers\Menu\DishController;
use App\Http\Controllers\Menu\DishOptionController;
use App\Http\Controllers\Menu\ServingSizeController;
use App\Http\Controllers\Restaurants\RestaurantController;
use App\Http\Controllers\Restaurants\RestaurantVerificationController;
use App\Http\Controllers\Rider\RiderOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
    Route::patch('companies/{company}', [CompanyController::class, 'update'])->name('companies.update');

    Route::get('companies/{company}/restaurants/create', [RestaurantController::class, 'create'])->name('restaurants.create');
    Route::post('companies/{company}/restaurants', [RestaurantController::class, 'store'])->name('restaurants.store');
    Route::get('restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
    Route::get('restaurants/{restaurant}/edit', [RestaurantController::class, 'edit'])->name('restaurants.edit');
    Route::patch('restaurants/{restaurant}', [RestaurantController::class, 'update'])->name('restaurants.update');
    Route::post('restaurants/{restaurant}/verify', RestaurantVerificationController::class)->name('restaurants.verify');

    Route::post('restaurants/{restaurant}/kitchens', [KitchenController::class, 'store'])->name('kitchens.store');
    Route::get('kitchens/{kitchen}/edit', [KitchenController::class, 'edit'])->name('kitchens.edit');
    Route::patch('kitchens/{kitchen}', [KitchenController::class, 'update'])->name('kitchens.update');

    Route::get('restaurants/{restaurant}/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('restaurants/{restaurant}/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('restaurants/{restaurant}/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');

    Route::get('restaurants/{restaurant}/dishes', [DishController::class, 'index'])->name('dishes.index');
    Route::get('restaurants/{restaurant}/dishes/create', [DishController::class, 'create'])->name('dishes.create');
    Route::post('restaurants/{restaurant}/dishes', [DishController::class, 'store'])->name('dishes.store');
    Route::get('dishes/{dish}/edit', [DishController::class, 'edit'])->name('dishes.edit');
    Route::patch('dishes/{dish}', [DishController::class, 'update'])->name('dishes.update');

    Route::post('dishes/{dish}/options', [DishOptionController::class, 'store'])->name('dish-options.store');
    Route::patch('dish-options/{dishOption}', [DishOptionController::class, 'update'])->name('dish-options.update');
    Route::delete('dish-options/{dishOption}', [DishOptionController::class, 'destroy'])->name('dish-options.destroy');

    Route::post('dishes/{dish}/serving-sizes', [ServingSizeController::class, 'store'])->name('serving-sizes.store');
    Route::patch('serving-sizes/{servingSize}', [ServingSizeController::class, 'update'])->name('serving-sizes.update');
    Route::delete('serving-sizes/{servingSize}', [ServingSizeController::class, 'destroy'])->name('serving-sizes.destroy');

    Route::get('chef/kitchens', [ChefKitchenController::class, 'index'])->name('chef.kitchens.index');
    Route::get('chef/orders', [ChefOrderController::class, 'index'])->name('chef.orders.index');
    Route::post('chef/orders/{order}/kitchens/{kitchen}/accept', [ChefOrderController::class, 'accept'])->name('chef.orders.accept');
    Route::patch('chef/orders/{order}/dishes/{orderDish}/status', [ChefOrderController::class, 'updateDish'])->name('chef.orders.dishes.update');

    Route::get('rider/orders', [RiderOrderController::class, 'index'])->name('rider.orders.index');
    Route::post('rider/orders/{order}/pickup', [RiderOrderController::class, 'pickup'])->name('rider.orders.pickup');
    Route::post('rider/orders/{order}/deliver', [RiderOrderController::class, 'deliver'])->name('rider.orders.deliver');
});
