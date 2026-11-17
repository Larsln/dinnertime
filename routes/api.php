<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\ServerController;
use App\Http\Controllers\Api\V1\StoreSectionController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\UnitController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('server', ServerController::class);
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/user', [AuthController::class, 'user']);

        Route::apiResource('recipes', RecipeController::class)->only(['index', 'show']);
        Route::apiResource('products', ProductController::class)->only(['index', 'show']);
        Route::get('store-sections', StoreSectionController::class);
        Route::get('tags', TagController::class);
        Route::get('units', UnitController::class);
    });
});
