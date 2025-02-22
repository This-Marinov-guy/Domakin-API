<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Common\FeedbackController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RentingController;
use App\Http\Controllers\SearchRentingController;
use App\Http\Controllers\ViewingController;
use App\Http\Controllers\Integration\WordPressController;

// Note: common routes with basic functionality
Route::prefix('common')->group(function () {
    Route::post('/newsletter/subscribe', [NewsletterController::class, 'create']);
    Route::delete('/newsletter/unsubscribe', [NewsletterController::class, 'destroy']);
});

Route::prefix('blog')->group(function () {
    Route::get('/posts', [WordPressController::class, 'getPosts']);
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
    Route::post('/create', [RentingController::class, 'create']);
});

Route::prefix('property')->group(function () {
    Route::post('/create', [PropertyController::class, 'create']);
});

// Authentication
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

Route::post('/validate-credentials', [RegisteredUserController::class, 'validate'])
->middleware('guest')
->name('validate-credentials');

// TODO: check which routes are needed
// Route::post('/login', [AuthenticatedSessionController::class, 'store'])
//     ->middleware('guest')
//     ->name('login');

// Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
//     ->middleware('guest')
//     ->name('password.email');

// Route::post('/reset-password', [NewPasswordController::class, 'store'])
//     ->middleware('guest')
//     ->name('password.store');

// Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
//     ->middleware(['auth', 'signed', 'throttle:6,1'])
//     ->name('verification.verify');

// Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
//     ->middleware(['auth', 'throttle:6,1'])
//     ->name('verification.send');

// Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
//     ->middleware('auth')
//     ->name('logout');
