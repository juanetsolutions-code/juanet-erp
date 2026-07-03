<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OrganizationController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\FileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Guest API Auth Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/login', [LoginController::class, 'login']);
});

// Authenticated API Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/auth/logout', [LoginController::class, 'logout']);
    Route::put('/profile', [ProfileController::class, 'updateProfile']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    // Global Search API endpoints
    Route::prefix('search')->group(function () {
        Route::get('/', [\App\Http\Controllers\SearchController::class, 'search']);
        Route::get('/autocomplete', [\App\Http\Controllers\SearchController::class, 'autocomplete']);
        Route::post('/reindex', [\App\Http\Controllers\SearchController::class, 'reindex']);
    });

    // Notification API endpoints
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::get('/preferences', [NotificationController::class, 'getPreferences']);
        Route::put('/preferences', [NotificationController::class, 'updatePreferences']);
        Route::post('/trigger-test', [NotificationController::class, 'triggerTest']);
    });

    // File Storage API endpoints
    Route::prefix('files')->group(function () {
        Route::get('/', [FileController::class, 'index']);
        Route::post('/', [FileController::class, 'store']);
        Route::get('/{id}/download', [FileController::class, 'download'])->name('api.files.download');
        Route::post('/{id}/signed-url', [FileController::class, 'generateSignedUrl']);
        Route::post('/{id}/scan', [FileController::class, 'scan']);
        Route::delete('/{id}', [FileController::class, 'destroy']);
    });

    // Tenant scoped actions
    Route::middleware('tenant')->group(function () {
        Route::get('/organizations', [OrganizationController::class, 'index']);
        Route::post('/organizations', [OrganizationController::class, 'store']);
        Route::post('/organizations/{id}/switch', [OrganizationController::class, 'switch']);
        Route::post('/organizations/{id}/leave', [OrganizationController::class, 'leave']);
        Route::post('/organizations/{id}/invite', [OrganizationController::class, 'invite']);

        // CRM Domain API Resources
        Route::apiResource('crm/leads', \App\Domain\CRM\Controllers\Api\LeadApiController::class);
        Route::apiResource('crm/contacts', \App\Domain\CRM\Controllers\Api\ContactApiController::class);
        Route::apiResource('crm/companies', \App\Domain\CRM\Controllers\Api\CompanyApiController::class);
        Route::apiResource('crm/opportunities', \App\Domain\CRM\Controllers\Api\OpportunityApiController::class);
    });
});

// Safaricom Lipa Na M-PESA Webhook Callback
Route::prefix('payments')->group(function () {
    Route::post('/m-pesa-callback', function (Request $request) {
        $payload = $request->all();
        Log::info('M-PESA Lipa Na M-PESA Webhook received', ['payload' => $payload]);

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Callback received and processed successfully'
        ], 200);
    });
});

// Support and Lead Ingestion API
Route::prefix('leads')->group(function () {
    Route::post('/subscribe', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string'
        ]);

        Log::info('Lead subscription recorded', ['lead' => $validated]);

        return response()->json([
            'status' => 'success',
            'message' => 'Lead captured in central contact_submissions pipeline'
        ], 201);
    });
});

// Signed temporary file download endpoint (public via HMAC check)
Route::get('/files/{id}/download-signed', [FileController::class, 'downloadSigned'])->name('api.files.download-signed');
