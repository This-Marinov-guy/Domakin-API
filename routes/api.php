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
    
});

Route::prefix('feedback')->group(function () {
    Route::get('/list', [FeedbackController::class, 'list']);
    Route::post('/create', [FeedbackController::class, 'create']);
    Route::put('/approve', [FeedbackController::class, 'approve']);
});

Route::prefix('viewing')->group(function () {
    Route::get('/list', [ViewingController::class, 'list']);
    Route::get('/details/{id}', [ViewingController::class, 'details']);
    Route::post('/create', [ViewingController::class, 'create']);
});