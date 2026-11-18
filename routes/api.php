<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\MealPlanController;
use App\Http\Controllers\Api\V1\PantryController;
use App\Http\Controllers\Api\V1\ProductAliasController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductConversionController;
use App\Http\Controllers\Api\V1\ProductPackageController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\RecipeMarkController;
use App\Http\Controllers\Api\V1\ServerController;
use App\Http\Controllers\Api\V1\ShoppingListController;
use App\Http\Controllers\Api\V1\ShoppingListItemController;
use App\Http\Controllers\Api\V1\StoreSectionController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\ToolController;
use App\Http\Controllers\Api\V1\UnitController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('server', ServerController::class);
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('auth/oidc', [AuthController::class, 'oidc'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/user', [AuthController::class, 'user']);

        Route::post('recipes/{recipe}/cover', [RecipeController::class, 'cover']);
        Route::put('recipes/{recipe}/rating', [RecipeMarkController::class, 'rate']);
        Route::put('recipes/{recipe}/favorite', [RecipeMarkController::class, 'favorite']);
        Route::apiResource('recipes', RecipeController::class);

        Route::post('imports/{import}/retry', [ImportController::class, 'retry']);
        Route::apiResource('imports', ImportController::class)->only(['index', 'store', 'show']);

        Route::post('tools/{tool}/merge', [ToolController::class, 'merge']);
        Route::apiResource('tools', ToolController::class)->except('show');

        Route::get('review', [ProductController::class, 'review']);
        Route::post('products/{product}/confirm', [ProductController::class, 'confirm']);
        Route::post('products/{product}/merge', [ProductController::class, 'merge']);
        Route::apiResource('products', ProductController::class);

        Route::put('products/{product}/conversions/{unit}', [ProductConversionController::class, 'update']);
        Route::delete('products/{product}/conversions/{unit}', [ProductConversionController::class, 'destroy']);
        Route::post('products/{product}/packages', [ProductPackageController::class, 'store']);
        Route::patch('product-packages/{package}', [ProductPackageController::class, 'update']);
        Route::delete('product-packages/{package}', [ProductPackageController::class, 'destroy']);
        Route::post('products/{product}/aliases', [ProductAliasController::class, 'store']);
        Route::delete('product-aliases/{alias}', [ProductAliasController::class, 'destroy']);

        Route::get('courses', CourseController::class);
        Route::get('store-sections', StoreSectionController::class);
        Route::get('tags', TagController::class);
        Route::get('units', UnitController::class);

        Route::post('meal-plan/suggest', [MealPlanController::class, 'suggest']);
        Route::post('meal-plan/batch', [MealPlanController::class, 'storeBatch']);
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
