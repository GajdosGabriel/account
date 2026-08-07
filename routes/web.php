<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeveloperController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrganizationAddressController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationLookupController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Back-office pre prevádzkovateľa
|--------------------------------------------------------------------------
| Zákazníci sa sem neprihlasujú – tí majú účty vo svojich projektoch
| a s Accountom komunikujú výhradne cez /api/v1.
*/

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    /* ---------------- Organizácie ---------------- */

    Route::resource('organizations', OrganizationController::class);

    Route::post('organizations/{organization}/restore', [OrganizationController::class, 'restore'])
        ->name('organizations.restore');

    Route::post('organizations/{organization}/products/{product}', [OrganizationController::class, 'toggleProduct'])
        ->name('organizations.products.toggle');
    Route::post('organizations/{organization}/subscribe', [OrganizationController::class, 'subscribe'])
        ->name('organizations.subscribe');
    Route::post('organizations/{organization}/{product}/cancel', [OrganizationController::class, 'cancelSubscription'])
        ->name('organizations.subscription.cancel');
    Route::post('organizations/{organization}/{product}/activate', [OrganizationController::class, 'activateSubscription'])
        ->name('organizations.subscription.activate');
    // Ďalšie adresy a kontaktné osoby
    Route::post('organizations/{organization}/addresses', [OrganizationAddressController::class, 'store'])
        ->name('organizations.addresses.store');
    Route::patch('organizations/{organization}/addresses/{address}', [OrganizationAddressController::class, 'update'])
        ->name('organizations.addresses.update');
    Route::delete('organizations/{organization}/addresses/{address}', [OrganizationAddressController::class, 'destroy'])
        ->name('organizations.addresses.destroy');

    Route::post('organizations/{organization}/contacts', [OrganizationAddressController::class, 'storeContact'])
        ->name('organizations.contacts.store');
    Route::delete('organizations/{organization}/contacts/{contact}', [OrganizationAddressController::class, 'destroyContact'])
        ->name('organizations.contacts.destroy');

    Route::post('organizations/{organization}/overrides', [OrganizationController::class, 'storeOverride'])
        ->name('organizations.overrides.store');
    Route::delete('organizations/{organization}/overrides/{override}', [OrganizationController::class, 'destroyOverride'])
        ->name('organizations.overrides.destroy');

    /* ---------------- Registre (AJAX) ---------------- */

    Route::post('lookup/ico', [OrganizationLookupController::class, 'ico'])->name('lookup.ico');
    Route::post('lookup/vat', [OrganizationLookupController::class, 'vat'])->name('lookup.vat');
    Route::post('organizations/{organization}/reverify', [OrganizationLookupController::class, 'reverify'])
        ->name('organizations.reverify');

    /* ---------------- Projekty, katalóg a cenníky ---------------- */

    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    Route::post('products/{product}/features', [ProductController::class, 'storeFeature'])->name('products.features.store');
    Route::patch('products/{product}/features/{feature}', [ProductController::class, 'updateFeature'])->name('products.features.update');
    Route::delete('products/{product}/features/{feature}', [ProductController::class, 'destroyFeature'])->name('products.features.destroy');

    Route::post('products/{product}/plans', [ProductController::class, 'storePlan'])->name('products.plans.store');
    Route::patch('products/{product}/plans/{plan}', [ProductController::class, 'updatePlan'])->name('products.plans.update');
    Route::delete('products/{product}/plans/{plan}', [ProductController::class, 'destroyPlan'])->name('products.plans.destroy');

    /* ---------------- Fakturácia ---------------- */

    // Export musí byť pred {invoice}, inak by "export" spadlo do model bindingu.
    Route::get('invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');

    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::patch('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

    // Položky konceptu
    Route::post('invoices/{invoice}/items', [InvoiceController::class, 'storeItem'])->name('invoices.items.store');
    Route::patch('invoices/{invoice}/items/{item}', [InvoiceController::class, 'updateItem'])->name('invoices.items.update');
    Route::delete('invoices/{invoice}/items/{item}', [InvoiceController::class, 'destroyItem'])->name('invoices.items.destroy');

    // Akcie nad dokladom – každú stráži InvoicePolicy
    Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('invoices/{invoice}/remind', [InvoiceController::class, 'remind'])->name('invoices.remind');
    Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::post('invoices/{invoice}/credit', [InvoiceController::class, 'credit'])->name('invoices.credit');
    Route::post('invoices/{invoice}/convert', [InvoiceController::class, 'convert'])->name('invoices.convert');
    Route::post('invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');

    // Kôš – binding musí vidieť aj zmazané záznamy
    Route::post('invoices/{invoice}/restore', [InvoiceController::class, 'restore'])
        ->withTrashed()->name('invoices.restore');
    Route::delete('invoices/{invoice}/force', [InvoiceController::class, 'forceDelete'])
        ->withTrashed()->name('invoices.force-delete');

    // Výstupy
    Route::get('invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'download'])->name('invoices.pdf');

    /* ---------------- Tokeny a webhooky ---------------- */

    Route::get('developers', [DeveloperController::class, 'index'])->name('developers.index');
    Route::post('developers/tokens', [DeveloperController::class, 'storeToken'])->name('developers.tokens.store');
    Route::delete('developers/tokens/{client}', [DeveloperController::class, 'revokeToken'])->name('developers.tokens.revoke');
    Route::post('developers/webhooks', [DeveloperController::class, 'storeWebhook'])->name('developers.webhooks.store');
    Route::delete('developers/webhooks/{endpoint}', [DeveloperController::class, 'destroyWebhook'])->name('developers.webhooks.destroy');

    /* ---------------- Nastavenia operátora ---------------- */

    Route::get('settings', [ProfileController::class, 'edit'])->name('settings.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('settings.profile.update');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('settings.password.update');
    Route::post('settings/operators', [ProfileController::class, 'storeOperator'])->name('settings.operators.store');
});
