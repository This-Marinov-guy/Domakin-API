<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Common\FeedbackController;
use App\Http\Controllers\Common\ViewingController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Note: common routes with basic functionality
Route::prefix('common')->group(function () {
    Route::get('/list-feedbacks', [FeedbackController::class, 'list']);
    Route::post('/create-feedback', [FeedbackController::class, 'create']);
});

Route::prefix('viewing')->group(function () {
    Route::get('/list-viewings', [ViewingController::class, 'list']);
    Route::get('/details/{id}', [ViewingController::class, 'details']);
    Route::post('/create-viewing', [ViewingController::class, 'create']);
});