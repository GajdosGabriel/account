<?php

use App\Http\Controllers\Api\EntitlementApiController;
use App\Http\Controllers\Api\OrganizationApiController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\UsageApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API pre pripojené projekty
|--------------------------------------------------------------------------
|
|   Authorization: Bearer acc_xxxxxxxx
|
| Token je viazaný na produkt, takže projekt vidí iba svoje firmy
| a svoje limity. Parameter za dvojbodkou je požadovaná abilita tokenu.
|
| Používatelia a heslá tu nie sú – tie zostávajú v projektoch.
|
*/

Route::prefix('v1')->group(function () {

    Route::middleware('service:organizations:read')->group(function () {
        Route::get('organizations', [OrganizationApiController::class, 'index']);
        Route::get('organizations/{organization}', [OrganizationApiController::class, 'show']);
        Route::post('organizations/lookup', [OrganizationApiController::class, 'lookup']);
    });

    Route::middleware('service:organizations:write')->group(function () {
        // najprv hlada podla ICO, az potom zaklada
        Route::post('organizations', [OrganizationApiController::class, 'store']);
        Route::put('organizations/{organization}', [OrganizationApiController::class, 'update']);
    });

    Route::middleware('service:entitlements:read')->group(function () {
        Route::get('organizations/{organization}/entitlements', [EntitlementApiController::class, 'show']);
    });

    Route::middleware('service:usage:write')->group(function () {
        Route::post('organizations/{organization}/usage', [UsageApiController::class, 'store']);
    });
});

/*
|--------------------------------------------------------------------------
| Prichádzajúce webhooky
|--------------------------------------------------------------------------
*/

Route::post('webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');
