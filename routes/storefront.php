<?php

use App\Http\Controllers\Storefront\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/r/{restaurant:slug}', [StorefrontController::class, 'show'])->name('storefront.show');
