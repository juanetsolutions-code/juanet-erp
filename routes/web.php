<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OrganizationController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\DashboardController;
use App\Domain\CRM\Controllers\Web\CrmWebController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/services', function () {
    return view('services');
})->name('services');

use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MarketplaceSearchController;

Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace');
Route::get('/marketplace/search', [MarketplaceSearchController::class, 'search'])->name('marketplace.search');
Route::get('/marketplace/category/{slug}', [MarketplaceController::class, 'categoryShow'])->name('marketplace.category');
Route::get('/marketplace/product/{slug}', [MarketplaceController::class, 'productShow'])->name('marketplace.product');
Route::post('/api/marketplace/track', [MarketplaceController::class, 'trackEvent'])->name('marketplace.track');

Route::get('/portfolio', function () {
    return view('portfolio');
})->name('portfolio');

Route::get('/blog', function () {
    return view('blog');
})->name('blog');

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

Route::get('/quote-request', function () {
    return view('quote-request');
})->name('quote-request');

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'framework' => 'Laravel 12',
        'php_version' => PHP_VERSION,
        'environment' => config('app.env'),
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);

    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

// Authenticated Session Protected Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Email Verification Routes
    Route::get('/email/verify', [VerificationController::class, 'showNotice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [VerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');

    // Profile & Sessions
    Route::get('/profile', [ProfileController::class, 'showProfileForm'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/logout-devices', [LoginController::class, 'logoutOtherDevices'])->name('profile.logout-devices');

    // Tenant/Organization Resolution Context Context Group
    Route::middleware(['tenant'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'webIndex'])->name('notifications.index');
        Route::get('/storage', [\App\Http\Controllers\FileController::class, 'webIndex'])->name('storage.index');
        Route::get('/search', [\App\Http\Controllers\SearchController::class, 'webIndex'])->name('search.index');

        // Organization Actions
        Route::get('/organizations', [OrganizationController::class, 'index'])->name('organization.index');
        Route::get('/organizations/create', [OrganizationController::class, 'create'])->name('organization.create');
        Route::post('/organizations', [OrganizationController::class, 'store'])->name('organization.store');
        Route::post('/organizations/{id}/switch', [OrganizationController::class, 'switch'])->name('organization.switch');
        Route::post('/organizations/{id}/leave', [OrganizationController::class, 'leave'])->name('organization.leave');
        Route::get('/organizations/{id}/settings', [OrganizationController::class, 'settings'])->name('organization.settings');
        Route::post('/organizations/{id}/settings', [OrganizationController::class, 'updateSettings'])->name('organization.settings.update');
        Route::post('/organizations/{id}/invite', [OrganizationController::class, 'invite'])->name('organization.invite');

        // Accept Invite Link
        Route::post('/organizations/invitation/{id}/accept', [OrganizationController::class, 'acceptInvite'])->name('organization.accept');

        // Enterprise Settings Platform Admin routes
        Route::get('/settings', [\App\Http\Controllers\SettingsAdminController::class, 'index'])->name('settings.index');
        Route::post('/settings/update', [\App\Http\Controllers\SettingsAdminController::class, 'updateSetting'])->name('settings.update');
        Route::post('/settings/delete', [\App\Http\Controllers\SettingsAdminController::class, 'deleteSetting'])->name('settings.delete');
        Route::post('/settings/flags', [\App\Http\Controllers\SettingsAdminController::class, 'updateFeatureFlag'])->name('settings.flags.update');
        Route::post('/settings/flags/delete', [\App\Http\Controllers\SettingsAdminController::class, 'deleteFeatureFlag'])->name('settings.flags.delete');
        Route::post('/settings/flags/beta/enroll', [\App\Http\Controllers\SettingsAdminController::class, 'enrollBeta'])->name('settings.flags.beta.enroll');
        Route::post('/settings/flags/beta/unenroll', [\App\Http\Controllers\SettingsAdminController::class, 'unenrollBeta'])->name('settings.flags.beta.unenroll');

        // CRM Module Routes
        Route::get('/crm', [CrmWebController::class, 'index'])->name('crm.leads.index');
        Route::get('/crm/leads/create', [CrmWebController::class, 'createLead'])->name('crm.leads.create');
        Route::post('/crm/leads', [CrmWebController::class, 'storeLead'])->name('crm.leads.store');
        Route::get('/crm/leads/{id}', [CrmWebController::class, 'showLead'])->name('crm.leads.show');
        Route::get('/crm/leads/{id}/edit', [CrmWebController::class, 'editLead'])->name('crm.leads.edit');
        Route::put('/crm/leads/{id}', [CrmWebController::class, 'updateLead'])->name('crm.leads.update');
        Route::delete('/crm/leads/{id}', [CrmWebController::class, 'destroyLead'])->name('crm.leads.destroy');
        Route::post('/crm/leads/{id}/assign', [CrmWebController::class, 'assignLead'])->name('crm.leads.assign');
        Route::get('/crm/leads/{id}/convert', [CrmWebController::class, 'convertLeadForm'])->name('crm.leads.convert.form');
        Route::post('/crm/leads/{id}/convert', [CrmWebController::class, 'convertLead'])->name('crm.leads.convert');
        Route::get('/crm/contacts', [CrmWebController::class, 'contacts'])->name('crm.contacts.index');
        Route::get('/crm/companies', [CrmWebController::class, 'companies'])->name('crm.companies.index');
        Route::get('/crm/opportunities', [CrmWebController::class, 'opportunities'])->name('crm.opportunities.index');
        Route::post('/crm/opportunities/move', [CrmWebController::class, 'moveOpportunity'])->name('crm.opportunities.move');
    });
});
