<?php

use App\Http\Controllers\V1\CategoryController;
use App\Http\Controllers\V1\PlaylistController;
use App\Http\Controllers\V1\VideoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/videos', [VideoController::class, 'index']);
    Route::get('/videos/{video}', [VideoController::class, 'show']);
    Route::get('/videos/{video}/watch', [VideoController::class, 'watch']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);

    Route::get('/playlists', [PlaylistController::class, 'index']);
    Route::get('/playlists/{playlist}', [PlaylistController::class, 'show']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/my-videos', [VideoController::class, 'myVideos']);
        Route::post('/videos', [VideoController::class, 'store']);
        Route::patch('/videos/{video}', [VideoController::class, 'update']);
        Route::delete('/videos/{video}', [VideoController::class, 'destroy']);

        Route::post('/categories', [CategoryController::class, 'store']);
        Route::patch('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::post('/playlists', [PlaylistController::class, 'store']);
        Route::patch('/playlists/{playlist}', [PlaylistController::class, 'update']);
        Route::delete('/playlists/{playlist}', [PlaylistController::class, 'destroy']);
        Route::post('/playlists/{playlist}/videos', [PlaylistController::class, 'addVideo']);
        Route::delete('/playlists/{playlist}/videos/{video}', [PlaylistController::class, 'removeVideo']);
    });
});
