<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MealPlanController;
use App\Http\Controllers\Api\V1\PantryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\ServerController;
use App\Http\Controllers\Api\V1\ShoppingListController;
use App\Http\Controllers\Api\V1\ShoppingListItemController;
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

        Route::post('meal-plan/generate', [MealPlanController::class, 'generate']);
        Route::apiResource('meal-plan', MealPlanController::class)
            ->parameters(['meal-plan' => 'entry'])
            ->except('show');

        Route::apiResource('pantry', PantryController::class)
            ->parameters(['pantry' => 'item'])
            ->except('show');

        Route::get('shopping-lists/current', [ShoppingListController::class, 'current']);
        Route::post('shopping-lists/{shoppingList}/stock-leftovers', [ShoppingListController::class, 'stockLeftovers']);
        Route::apiResource('shopping-lists', ShoppingListController::class)
            ->parameters(['shopping-lists' => 'shoppingList'])
            ->only(['store', 'show']);

        Route::post('shopping-lists/{shoppingList}/items', [ShoppingListItemController::class, 'store']);
        Route::patch('shopping-list-items/{item}', [ShoppingListItemController::class, 'update']);
        Route::delete('shopping-list-items/{item}', [ShoppingListItemController::class, 'destroy']);
    });
});
