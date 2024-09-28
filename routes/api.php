<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Common\FeedbackController;
use App\Http\Controllers\Common\ViewingController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('common')->group(function () {
    Route::get('/list-feedbacks', [FeedbackController::class, 'list']);
    Route::post('/create-feedback', [FeedbackController::class, 'create']);

    Route::get('/viewing/list', [ViewingController::class, 'list']);
    Route::get('/viewing/details/{id}', [ViewingController::class, 'details']);
    Route::post('/viewing/create', [ViewingController::class, 'create']);
});