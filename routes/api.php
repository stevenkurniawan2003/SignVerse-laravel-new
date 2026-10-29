<?php

use App\Http\Controllers\Api\SignTechApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SignTech API Routes (Requires Authentication — Data from Real Database)
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('v1')->group(function () {
        Route::get('/dashboard', [SignTechApiController::class, 'dashboard']);
        Route::get('/materials', [SignTechApiController::class, 'materials']);
        Route::get('/lessons/{id}', [SignTechApiController::class, 'getLesson']);
        Route::post('/lessons/{id}/complete', [SignTechApiController::class, 'completeLesson']);
        Route::get('/practice/targets', [SignTechApiController::class, 'practiceTargets']);
        Route::post('/practice/attempt', [SignTechApiController::class, 'attemptPractice']);
        Route::get('/challenges', [SignTechApiController::class, 'challenges']);
        Route::post('/challenges/claim', [SignTechApiController::class, 'claimChallenge']);
        Route::get('/achievements', [SignTechApiController::class, 'achievements']);
        Route::get('/classroom', [SignTechApiController::class, 'classroom']);
    });

    Route::get('/dashboard', [SignTechApiController::class, 'dashboard']);
    Route::get('/materials', [SignTechApiController::class, 'materials']);
    Route::get('/lessons/{id}', [SignTechApiController::class, 'getLesson']);
    Route::post('/lessons/{id}/complete', [SignTechApiController::class, 'completeLesson']);
    Route::get('/practice/targets', [SignTechApiController::class, 'practiceTargets']);
    Route::post('/practice/attempt', [SignTechApiController::class, 'attemptPractice']);
    Route::get('/challenges', [SignTechApiController::class, 'challenges']);
    Route::post('/challenges/claim', [SignTechApiController::class, 'claimChallenge']);
    Route::get('/achievements', [SignTechApiController::class, 'achievements']);
    Route::get('/classroom', [SignTechApiController::class, 'classroom']);
});
