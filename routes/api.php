<?php

use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Middleware\AuthorizationMiddleware;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CareerController;
use App\Http\Controllers\Common\FeedbackController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RentingController;
use App\Http\Controllers\SearchRentingController;
use App\Http\Controllers\ViewingController;
use App\Http\Controllers\Integration\WordPressController;
use App\Http\Controllers\Webhook\StripeWebhookController;
use App\Http\Controllers\ListingApplicationController;
use App\Http\Controllers\Test;

/*
|--------------------------------------------------------------------------
| API Routes - Versioned
|--------------------------------------------------------------------------
|
| All API routes are versioned. Current version: v1
| To add a new version (e.g., v2), create a new route group below.
|
*/

// API Version 1
Route::prefix('v1')->group(function () {
    // Note: common routes with basic functionality
    Route::prefix('common')->group(function () {
        Route::post('/newsletter/subscribe', [NewsletterController::class, 'create']);
        Route::delete('/newsletter/unsubscribe', [NewsletterController::class, 'destroy']);
    });

    Route::prefix('blog')->group(function () {
        Route::get('/posts', [WordPressController::class, 'getPosts']);
        Route::get('/post-by-slug/{slug}', [WordPressController::class, 'getPostBySlug']);
        Route::get('/post/{id}', [WordPressController::class, 'getPostDetails']);
    });

    Route::prefix('feedback')->group(function () {
        Route::get('/list', [FeedbackController::class, 'list']);
        Route::post('/create', [FeedbackController::class, 'create']);
        Route::put('/approve', [FeedbackController::class, 'approve']);
    });

    // Service
    Route::prefix('viewing')->group(function () {
        Route::get('/list', [ViewingController::class, 'list']);
        Route::get('/details/{id}', [ViewingController::class, 'details']);
        Route::post('/create', [ViewingController::class, 'create']);
    });

    Route::prefix('renting')->group(function () {
        Route::post('/searching/create', [SearchRentingController::class, 'create']);
        Route::post('/create', [RentingController::class, 'create'])->middleware('domain.whitelist');
        Route::get('/list', [RentingController::class, 'list'])->middleware('auth.role:admin');
        Route::get('/list-by-property/{id}', [RentingController::class, 'show'])->middleware('auth.role:admin');
        Route::patch('/edit', [RentingController::class, 'edit'])->middleware('auth.role:admin');
    });

    Route::prefix('career')->group(function () {
        Route::post('/apply', [CareerController::class, 'apply']);
    });

    Route::prefix('property')->group(function () {
        Route::get('/list-extended', [PropertyController::class, 'fetchAllProperties'])
            ->middleware('auth.role:admin');

        Route::get('/list', [PropertyController::class, 'fetchUserProperties'])
            ->middleware('auth.role');

        Route::get('/details/{id}', [PropertyController::class, 'details']);

        Route::get('/listing', [PropertyController::class, 'show']);
        // ->middleware('domain.whitelist'); FIX
        Route::get('/listing.xml', [PropertyController::class, 'listingXml']); // Public: open in browser to get XML (like /api/documentation)

        Route::post('/create', [PropertyController::class, 'create']);
        Route::post('/edit', [PropertyController::class, 'edit'])->middleware('auth.role:admin');
        Route::delete('/delete', [PropertyController::class, 'delete']);

        // Route::match(methods: ['GET', 'POST', 'OPTIONS'], '/signal-test', [PropertyController::class, 'testSignalIntegration']);
        Route::post('/payment/create-link', [PropertyController::class, 'createPaymentLink'])->middleware('auth.role:admin');
    });

    Route::prefix('listing-application')->group(function () {
        // Validation (step-by-step, no auth required)
        Route::post('/validate/step-2', [ListingApplicationController::class, 'validateStep2']);
        Route::post('/validate/step-3', [ListingApplicationController::class, 'validateStep3']);
        Route::post('/validate/step-4', [ListingApplicationController::class, 'validateStep4']);
        Route::post('/validate/step-5', [ListingApplicationController::class, 'validateStep5']);


        Route::post('/save',   [ListingApplicationController::class, 'save']);
        Route::post('/submit', [ListingApplicationController::class, 'submit']);

        Route::get('/list',      [ListingApplicationController::class, 'list'])->middleware('auth.role');
        Route::get('/list-extended',  [ListingApplicationController::class, 'listAll'])->middleware('auth.role:admin');
        Route::get('/{referenceId}', [ListingApplicationController::class, 'show']);

        // Mutate (auth required)
        Route::patch('/edit',    [ListingApplicationController::class, 'edit'])->middleware('auth.role');
        Route::delete('/delete', [ListingApplicationController::class, 'destroy'])->middleware('auth.role');
    });

    Route::prefix('authentication')->group(function () {
        Route::post('/register', [RegisteredUserController::class, 'store'])
            ->middleware('guest')
            ->name('register');

        Route::post('/validate-credentials', [RegisteredUserController::class, 'validate'])
            ->middleware('guest')
            ->name('validate-credentials');
    });

    // Authentication
    Route::prefix('user')->group(function () {
        Route::post('/edit-details', [ProfileController::class, 'edit'])
            ->middleware('auth.role')
            ->name('edit-user-details');
    });
});

// Stripe webhook (no versioning, legacy support)
Route::post('/webhooks/stripe/checkout', [StripeWebhookController::class, 'handle']);

/*
|--------------------------------------------------------------------------
| Future API Versions
|--------------------------------------------------------------------------
|
| To add a new API version (e.g., v2), uncomment and modify the section below:
|
| Route::prefix('v2')->group(function () {
|     // Add v2 specific routes here
|     // You can reuse controllers or create new versioned controllers
| });
|
*/
