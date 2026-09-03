<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeveloperController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LocaleController;
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

// Jazyk sa prepína aj pred prihlásením – prihlasovacia obrazovka je
// prvé, čo človek uvidí, a už tá má byť v jeho jazyku.
Route::post('locale', LocaleController::class)->name('locale.update');

// Potvrdenie fakturačného e-mailu. Klikne naň zákazník, ktorý sem prístup
// nemá a mať nemá – autentizáciu preto rieši podpis v adrese. Binding musí
// vidieť aj zmazané: firma sa medzitým mohla dostať do koša a odkaz má
// aj tak povedať niečo zrozumiteľné.
Route::get('organizations/{organization}/billing-email/verify', [OrganizationController::class, 'verifyBillingEmail'])
    ->middleware(['signed', 'throttle:10,1'])
    ->withTrashed()
    ->name('organizations.billing-email.verify');

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

    // Úprava je povolená aj pre záznam v koši (opraviť ho ešte pred
    // návratom), takže binding musí vidieť aj zmazané.
    Route::resource('organizations', OrganizationController::class)
        ->withTrashed(['show', 'edit', 'update']);

    Route::post('organizations/{organization}/restore', [OrganizationController::class, 'restore'])
        ->name('organizations.restore');
    Route::delete('organizations/{organization}/force', [OrganizationController::class, 'forceDelete'])
        ->withTrashed()->name('organizations.force-delete');

    Route::post('organizations/{organization}/billing-email/resend', [OrganizationController::class, 'resendBillingEmailVerification'])
        ->name('organizations.billing-email.resend');

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
        ->withTrashed()->name('organizations.addresses.update');
    Route::delete('organizations/{organization}/addresses/{address}', [OrganizationAddressController::class, 'destroy'])
        ->name('organizations.addresses.destroy');
    Route::post('organizations/{organization}/addresses/{address}/restore', [OrganizationAddressController::class, 'restore'])
        ->withTrashed()->name('organizations.addresses.restore');
    Route::delete('organizations/{organization}/addresses/{address}/force', [OrganizationAddressController::class, 'forceDelete'])
        ->withTrashed()->name('organizations.addresses.force-delete');

    Route::post('organizations/{organization}/contacts', [OrganizationAddressController::class, 'storeContact'])
        ->name('organizations.contacts.store');
    Route::patch('organizations/{organization}/contacts/{contact}', [OrganizationAddressController::class, 'updateContact'])
        ->withTrashed()->name('organizations.contacts.update');
    Route::delete('organizations/{organization}/contacts/{contact}', [OrganizationAddressController::class, 'destroyContact'])
        ->name('organizations.contacts.destroy');
    Route::post('organizations/{organization}/contacts/{contact}/restore', [OrganizationAddressController::class, 'restoreContact'])
        ->withTrashed()->name('organizations.contacts.restore');
    Route::delete('organizations/{organization}/contacts/{contact}/force', [OrganizationAddressController::class, 'forceDeleteContact'])
        ->withTrashed()->name('organizations.contacts.force-delete');

    Route::post('organizations/{organization}/overrides', [OrganizationController::class, 'storeOverride'])
        ->name('organizations.overrides.store');
    Route::delete('organizations/{organization}/overrides/{override}', [OrganizationController::class, 'destroyOverride'])
        ->name('organizations.overrides.destroy');
    Route::post('organizations/{organization}/overrides/{override}/restore', [OrganizationController::class, 'restoreOverride'])
        ->withTrashed()->name('organizations.overrides.restore');
    Route::delete('organizations/{organization}/overrides/{override}/force', [OrganizationController::class, 'forceDeleteOverride'])
        ->withTrashed()->name('organizations.overrides.force-delete');

    /* ---------------- Registre (AJAX) ---------------- */

    Route::post('lookup/ico', [OrganizationLookupController::class, 'ico'])->name('lookup.ico');
    Route::post('lookup/vat', [OrganizationLookupController::class, 'vat'])->name('lookup.vat');
    Route::post('organizations/{organization}/reverify', [OrganizationLookupController::class, 'reverify'])
        ->name('organizations.reverify');

    /* ---------------- Projekty, katalóg a cenníky ---------------- */

    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::get('products/{product}', [ProductController::class, 'show'])->withTrashed()->name('products.show');
    Route::patch('products/{product}', [ProductController::class, 'update'])->withTrashed()->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::post('products/{product}/restore', [ProductController::class, 'restore'])
        ->withTrashed()->name('products.restore');
    Route::delete('products/{product}/force', [ProductController::class, 'forceDelete'])
        ->withTrashed()->name('products.force-delete');

    Route::post('products/{product}/features', [ProductController::class, 'storeFeature'])->name('products.features.store');
    Route::patch('products/{product}/features/{feature}', [ProductController::class, 'updateFeature'])
        ->withTrashed()->name('products.features.update');
    Route::delete('products/{product}/features/{feature}', [ProductController::class, 'destroyFeature'])->name('products.features.destroy');
    Route::post('products/{product}/features/{feature}/restore', [ProductController::class, 'restoreFeature'])
        ->withTrashed()->name('products.features.restore');
    Route::delete('products/{product}/features/{feature}/force', [ProductController::class, 'forceDeleteFeature'])
        ->withTrashed()->name('products.features.force-delete');

    Route::post('products/{product}/plans', [ProductController::class, 'storePlan'])->name('products.plans.store');
    Route::patch('products/{product}/plans/{plan}', [ProductController::class, 'updatePlan'])
        ->withTrashed()->name('products.plans.update');
    Route::delete('products/{product}/plans/{plan}', [ProductController::class, 'destroyPlan'])->name('products.plans.destroy');
    Route::post('products/{product}/plans/{plan}/restore', [ProductController::class, 'restorePlan'])
        ->withTrashed()->name('products.plans.restore');
    Route::delete('products/{product}/plans/{plan}/force', [ProductController::class, 'forceDeletePlan'])
        ->withTrashed()->name('products.plans.force-delete');

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
    Route::get('developers/tokens/{client}/edit', [DeveloperController::class, 'editToken'])->name('developers.tokens.edit');
    Route::patch('developers/tokens/{client}', [DeveloperController::class, 'updateToken'])->name('developers.tokens.update');
    Route::post('developers/tokens/{client}/revoke', [DeveloperController::class, 'revokeToken'])->name('developers.tokens.revoke');
    Route::post('developers/tokens/{client}/unrevoke', [DeveloperController::class, 'unrevokeToken'])->name('developers.tokens.unrevoke');
    Route::delete('developers/tokens/{client}', [DeveloperController::class, 'destroyToken'])->name('developers.tokens.destroy');

    Route::post('developers/webhooks', [DeveloperController::class, 'storeWebhook'])->name('developers.webhooks.store');
    Route::patch('developers/webhooks/{endpoint}', [DeveloperController::class, 'updateWebhook'])->name('developers.webhooks.update');
    Route::delete('developers/webhooks/{endpoint}', [DeveloperController::class, 'destroyWebhook'])->name('developers.webhooks.destroy');

    /* ---------------- Nastavenia operátora ---------------- */

    Route::get('settings', [ProfileController::class, 'edit'])->name('settings.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('settings.profile.update');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('settings.password.update');
    Route::post('settings/operators', [ProfileController::class, 'storeOperator'])->name('settings.operators.store');
});
