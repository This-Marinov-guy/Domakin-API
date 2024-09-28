<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Common\FeedbackController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('common')->group(function () {
    Route::get('/list-feedbacks', [FeedbackController::class, 'list']);
    Route::post('/create-feedback', [FeedbackController::class, 'create']);
});
