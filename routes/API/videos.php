<?php

use App\Http\Controllers\V1\CategoryController;
use App\Http\Controllers\V1\PlaylistController;
use App\Http\Controllers\V1\VideoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/videos', [VideoController::class, 'index']);
    Route::get('/videos/{video}', [VideoController::class, 'show']);
    Route::get('/videos/{video}/watch', [VideoController::class, 'watch']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/my-videos', [VideoController::class, 'myVideos']);
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/videos', [VideoController::class, 'store']);
        Route::patch('/videos/{video}', [VideoController::class, 'update']);
        Route::delete('/videos/{video}', [VideoController::class, 'destroy']);
    });
});
