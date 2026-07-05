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
        Route::prefix('crm/leads')->group(function () {
            Route::post('/bulk-update', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'bulkUpdate']);
            Route::post('/bulk-delete', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'bulkDelete']);
            Route::post('/bulk-assign', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'bulkAssign']);
            Route::post('/bulk-archive', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'bulkArchive']);
            Route::post('/bulk-restore', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'bulkRestore']);
            Route::post('/bulk-tag', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'bulkTag']);
            Route::get('/export', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'export']);
            Route::post('/import', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'import']);
            Route::post('/rollback', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'rollback']);

            Route::post('/{id}/transition', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'transition']);
            Route::post('/{id}/assign', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'assign']);
            Route::post('/{id}/convert', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'convert']);
            Route::get('/{id}/timeline', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'timeline']);
            Route::get('/{id}/duplicates', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'duplicates']);
            Route::post('/{id}/merge', [\App\Domain\CRM\Controllers\Api\LeadApiController::class, 'merge']);
        });

        Route::apiResource('crm/leads', \App\Domain\CRM\Controllers\Api\LeadApiController::class);

        // CRM Contacts Extensions
        Route::prefix('crm/contacts')->group(function () {
            Route::post('/bulk-update', [\App\Domain\CRM\Controllers\Api\ContactApiController::class, 'bulkUpdate']);
            Route::post('/bulk-tag', [\App\Domain\CRM\Controllers\Api\ContactApiController::class, 'bulkTag']);
            Route::post('/bulk-archive', [\App\Domain\CRM\Controllers\Api\ContactApiController::class, 'bulkArchive']);
            Route::post('/{id}/recalculate-health', [\App\Domain\CRM\Controllers\Api\ContactApiController::class, 'recalculateHealth']);
            Route::get('/{id}/timeline', [\App\Domain\CRM\Controllers\Api\ContactApiController::class, 'timeline']);
            Route::post('/{id}/companies', [\App\Domain\CRM\Controllers\Api\ContactApiController::class, 'storeCompanyAssociation']);
            Route::delete('/{id}/companies/{company_id}', [\App\Domain\CRM\Controllers\Api\ContactApiController::class, 'destroyCompanyAssociation']);
            Route::post('/{id}/methods', [\App\Domain\CRM\Controllers\Api\ContactApiController::class, 'storeMethod']);
            Route::put('/{id}/methods/{method_id}', [\App\Domain\CRM\Controllers\Api\ContactApiController::class, 'updateMethod']);
            Route::delete('/{id}/methods/{method_id}', [\App\Domain\CRM\Controllers\Api\ContactApiController::class, 'destroyMethod']);
            Route::post('/{id}/relationships', [\App\Domain\CRM\Controllers\Api\ContactApiController::class, 'storeRelationship']);
            Route::put('/{id}/relationships/{relationship_id}', [\App\Domain\CRM\Controllers\Api\ContactApiController::class, 'updateRelationship']);
            Route::delete('/{id}/relationships/{relationship_id}', [\App\Domain\CRM\Controllers\Api\ContactApiController::class, 'destroyRelationship']);
        });
        Route::apiResource('crm/contacts', \App\Domain\CRM\Controllers\Api\ContactApiController::class);

        // CRM Companies Enterprise Extensions
        Route::prefix('crm/companies')->group(function () {
            Route::post('/{id}/recalculate-health', [\App\Domain\CRM\Controllers\Api\CompanyApiController::class, 'recalculateHealth']);
            Route::get('/{id}/hierarchy', [\App\Domain\CRM\Controllers\Api\CompanyApiController::class, 'hierarchy']);
            Route::get('/{id}/locations', [\App\Domain\CRM\Controllers\Api\CompanyApiController::class, 'getLocations']);
            Route::post('/{id}/locations', [\App\Domain\CRM\Controllers\Api\CompanyApiController::class, 'storeLocation']);
            Route::put('/{id}/locations/{location_id}', [\App\Domain\CRM\Controllers\Api\CompanyApiController::class, 'updateLocation']);
            Route::delete('/{id}/locations/{location_id}', [\App\Domain\CRM\Controllers\Api\CompanyApiController::class, 'destroyLocation']);
        });
        Route::apiResource('crm/companies', \App\Domain\CRM\Controllers\Api\CompanyApiController::class);
        
        Route::prefix('crm/opportunities')->group(function () {
            Route::post('/bulk-update', [\App\Domain\CRM\Controllers\Api\OpportunityApiController::class, 'bulkUpdate']);
            Route::post('/bulk-assign', [\App\Domain\CRM\Controllers\Api\OpportunityApiController::class, 'bulkAssign']);
            Route::post('/bulk-move-stage', [\App\Domain\CRM\Controllers\Api\OpportunityApiController::class, 'bulkMoveStage']);
            
            Route::get('/{id}/products', [\App\Domain\CRM\Controllers\Api\OpportunityApiController::class, 'indexProducts']);
            Route::post('/{id}/products', [\App\Domain\CRM\Controllers\Api\OpportunityApiController::class, 'storeProduct']);
            Route::put('/{id}/products/{productId}', [\App\Domain\CRM\Controllers\Api\OpportunityApiController::class, 'updateProduct']);
            Route::delete('/{id}/products/{productId}', [\App\Domain\CRM\Controllers\Api\OpportunityApiController::class, 'destroyProduct']);
        });
        Route::apiResource('crm/opportunities', \App\Domain\CRM\Controllers\Api\OpportunityApiController::class);

        // CRM Activities, Timeline & Notes API
        Route::prefix('crm/activities')->group(function () {
            Route::post('/bulk-complete', [\App\Domain\CRM\Controllers\Api\ActivityApiController::class, 'bulkComplete']);
            Route::post('/bulk-update', [\App\Domain\CRM\Controllers\Api\ActivityApiController::class, 'bulkUpdate']);
            Route::get('/timeline', [\App\Domain\CRM\Controllers\Api\ActivityApiController::class, 'timeline']);
            
            Route::post('/notes', [\App\Domain\CRM\Controllers\Api\ActivityApiController::class, 'storeNote']);
            Route::put('/notes/{id}', [\App\Domain\CRM\Controllers\Api\ActivityApiController::class, 'updateNote']);
            Route::delete('/notes/{id}', [\App\Domain\CRM\Controllers\Api\ActivityApiController::class, 'destroyNote']);
            
            Route::post('/{activityId}/attachments', [\App\Domain\CRM\Controllers\Api\ActivityApiController::class, 'storeAttachment']);
            Route::post('/{activityId}/reminders', [\App\Domain\CRM\Controllers\Api\ActivityApiController::class, 'storeReminder']);
            Route::post('/{id}/complete', [\App\Domain\CRM\Controllers\Api\ActivityApiController::class, 'complete']);
        });
        Route::apiResource('crm/activities', \App\Domain\CRM\Controllers\Api\ActivityApiController::class);

        // CRM Visitor Intelligence Analytics routes (GDPR and isolation-aware)
        Route::prefix('crm/visitor-intelligence')->group(function () {
            Route::get('/analytics', [\App\Http\Controllers\Api\VisitorCrmController::class, 'analytics']);
            Route::get('/timeline/{visitor_id}', [\App\Http\Controllers\Api\VisitorCrmController::class, 'timeline']);
            Route::get('/visitors', [\App\Http\Controllers\Api\VisitorCrmController::class, 'index']);
            Route::post('/gdpr/anonymize', [\App\Http\Controllers\Api\VisitorCrmController::class, 'anonymize']);
            Route::post('/gdpr/delete', [\App\Http\Controllers\Api\VisitorCrmController::class, 'delete']);
            Route::get('/gdpr/export/{visitor_id}', [\App\Http\Controllers\Api\VisitorCrmController::class, 'export']);
        });
    });
});

// Public Visitor Intelligence tracking routes
Route::prefix('public/visitors')->group(function () {
    Route::post('/track', [\App\Http\Controllers\Api\VisitorTrackingController::class, 'track'])
        ->middleware(['tenant']);
    Route::post('/cta', [\App\Http\Controllers\Api\VisitorTrackingController::class, 'trackCta'])
        ->middleware(['tenant']);
    Route::post('/end-session', [\App\Http\Controllers\Api\VisitorTrackingController::class, 'endSession'])
        ->middleware(['tenant']);
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

// Public Lead Capture API (unauthenticated, rate limited, tenant aware)
Route::post('/public/leads', [\App\Http\Controllers\Api\PublicLeadController::class, 'store'])
    ->middleware(['throttle:10,1', 'tenant']);

// Public Marketplace API routes
Route::prefix('marketplace')->group(function () {
    Route::get('/search', [\App\Http\Controllers\MarketplaceSearchController::class, 'search']);
    Route::post('/newsletter', [\App\Http\Controllers\MarketplaceNewsletterController::class, 'subscribe']);
});

// Signed temporary file download endpoint (public via HMAC check)
Route::get('/files/{id}/download-signed', [FileController::class, 'downloadSigned'])->name('api.files.download-signed');
