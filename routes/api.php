<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\DinnerRoundController;
use App\Http\Controllers\Api\V1\HouseholdSettingsController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\ImportSettingsController;
use App\Http\Controllers\Api\V1\MealPlanController;
use App\Http\Controllers\Api\V1\MealPlanReasonController;
use App\Http\Controllers\Api\V1\MealPlanShiftController;
use App\Http\Controllers\Api\V1\MealSideController;
use App\Http\Controllers\Api\V1\PantryController;
use App\Http\Controllers\Api\V1\PortionFeedbackController;
use App\Http\Controllers\Api\V1\ProductAliasController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductConversionController;
use App\Http\Controllers\Api\V1\ProductOfferController;
use App\Http\Controllers\Api\V1\RealtimeController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\RecipeMarkController;
use App\Http\Controllers\Api\V1\ServerController;
use App\Http\Controllers\Api\V1\ShoppingListController;
use App\Http\Controllers\Api\V1\ShoppingListItemController;
use App\Http\Controllers\Api\V1\SideKindController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\StoreSectionController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\ToolController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Middleware\HonorActionTime;
use App\Http\Middleware\ReplayRepeatedRequests;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('server', ServerController::class);
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('auth/oidc', [AuthController::class, 'oidc'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', ReplayRepeatedRequests::class, HonorActionTime::class])->group(function () {
        Route::get('sync', SyncController::class);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/user', [AuthController::class, 'user']);

        Route::get('recipes/{recipe}/sides', [MealSideController::class, 'index']);
        Route::post('recipes/{recipe}/cover', [RecipeController::class, 'cover']);
        Route::put('recipes/{recipe}/rating', [RecipeMarkController::class, 'rate'])->withoutMiddleware(HonorActionTime::class);
        Route::put('recipes/{recipe}/favorite', [RecipeMarkController::class, 'favorite'])->withoutMiddleware(HonorActionTime::class);
        Route::apiResource('recipes', RecipeController::class);

        Route::get('realtime', RealtimeController::class);

        Route::get('settings/household', [HouseholdSettingsController::class, 'show']);
        Route::patch('settings/household', [HouseholdSettingsController::class, 'update']);
        Route::get('settings/import', [ImportSettingsController::class, 'show']);
        Route::patch('settings/import', [ImportSettingsController::class, 'update']);
        Route::post('settings/import/cookies', [ImportSettingsController::class, 'storeCookies']);
        Route::delete('settings/import/cookies', [ImportSettingsController::class, 'destroyCookies']);

        Route::post('imports/{import}/retry', [ImportController::class, 'retry']);
        Route::get('imports/{import}/wait', [ImportController::class, 'wait']);
        Route::apiResource('imports', ImportController::class)->only(['index', 'store', 'show']);

        Route::post('tools/{tool}/merge', [ToolController::class, 'merge']);
        Route::apiResource('tools', ToolController::class)->except('show');

        Route::get('review', [ProductController::class, 'review']);
        Route::post('products/{product}/confirm', [ProductController::class, 'confirm']);
        Route::post('products/{product}/merge', [ProductController::class, 'merge']);
        Route::apiResource('products', ProductController::class);

        Route::put('products/{product}/conversions/{unit}', [ProductConversionController::class, 'update']);
        Route::delete('products/{product}/conversions/{unit}', [ProductConversionController::class, 'destroy']);
        Route::apiResource('stores', StoreController::class)->except('show');
        Route::post('products/{product}/offers', [ProductOfferController::class, 'store']);
        Route::patch('product-offers/{offer}', [ProductOfferController::class, 'update']);
        Route::delete('product-offers/{offer}', [ProductOfferController::class, 'destroy']);
        Route::post('products/{product}/aliases', [ProductAliasController::class, 'store']);
        Route::delete('product-aliases/{alias}', [ProductAliasController::class, 'destroy']);

        Route::get('dinner-rounds/current', [DinnerRoundController::class, 'current']);
        Route::post('dinner-rounds', [DinnerRoundController::class, 'store']);
        Route::post('dinner-rounds/{dinnerRound}/votes', [DinnerRoundController::class, 'vote']);
        Route::post('dinner-rounds/{dinnerRound}/plan', [DinnerRoundController::class, 'plan']);
        Route::delete('dinner-rounds/{dinnerRound}', [DinnerRoundController::class, 'destroy'])->withoutMiddleware(HonorActionTime::class);

        Route::get('courses', CourseController::class);
        Route::get('side-kinds', SideKindController::class);
        Route::get('store-sections', StoreSectionController::class);
        Route::get('tags', TagController::class);
        Route::get('units', UnitController::class);

        Route::put('meal-plan/{entry}/portion-feedback', [PortionFeedbackController::class, 'update'])->withoutMiddleware(HonorActionTime::class);
        Route::post('meal-plan/{entry}/sides', [MealSideController::class, 'store']);
        Route::post('meal-plan/suggest', [MealPlanController::class, 'suggest']);
        Route::post('meal-plan/batch', [MealPlanController::class, 'storeBatch']);
        Route::post('meal-plan/shift', MealPlanShiftController::class);
        Route::get('meal-plan/reasons', MealPlanReasonController::class);
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
