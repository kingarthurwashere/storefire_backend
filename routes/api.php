<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\MarketingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductRetrievalController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\ViewController;
use App\Http\Controllers\Api\ScrapeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'checkout'], function () {
    Route::get('/payment-methods', [CheckoutController::class, 'payment_methods']);
});

Route::group(['prefix' => 'marketing'], function () {
    Route::get('discover-items', [MarketingController::class, 'discoverItems']);
    Route::get('popular-items', [MarketingController::class, 'popularItems']);
    Route::get('recent-items', [MarketingController::class, 'recentItems']);
    Route::get('trending-items', [MarketingController::class, 'trendingItems']);
    Route::get('amazonae-items', [MarketingController::class, 'amazonaeItems']);
    Route::get('aliexpress-items', [MarketingController::class, 'aliexpressItems']);
});

Route::group(['prefix' => 'orders'], function () {
    Route::get('/{order}', [OrderController::class, 'view']);
    Route::post('/place', [OrderController::class, 'place']);

    Route::get('/', OrderController::class)->middleware(['auth:sanctum']);
    Route::post('/', [OrderController::class, 'placeForAuthenticatedUser'])->middleware(['auth:sanctum']);
});

Route::group(['prefix' => 'products'], function () {
    Route::post('retrieve', ProductRetrievalController::class);
});

Route::group(['prefix' => 'users'], function () {
    Route::get('auth', AuthController::class)->middleware(['auth:sanctum']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('register', [AuthController::class, 'register']);
});

Route::group(['prefix' => 'store'], function () {
    Route::post('search', ScrapeController::class);
    Route::post('upload', UploadController::class);
    Route::post('upload/multiple', [UploadController::class, 'multiple']);
    Route::get('{identifier}', ViewController::class);
});
