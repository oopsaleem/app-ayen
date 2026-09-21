<?php

use App\Http\Controllers\Companies\CompanyController;
use App\Http\Controllers\Restaurants\RestaurantController;
use App\Http\Controllers\Restaurants\RestaurantVerificationController;
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
});
