<?php

use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Middleware\AuthorizationMiddleware;
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
    Route::get('/show', [PropertyController::class, 'show']);
    Route::post('/update', [PropertyController::class, 'update']);
    Route::delete('/delete', [PropertyController::class, 'delete']);
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
        ->middleware(AuthorizationMiddleware::class)
        ->name('edit-user-details');
});
