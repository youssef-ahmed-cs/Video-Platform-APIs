<?php

use App\Http\Controllers\V1\CategoryController;
use App\Http\Controllers\V1\PlaylistController;
use App\Http\Controllers\V1\VideoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/playlists', [PlaylistController::class, 'index']);
    Route::get('/playlists/{playlist}', [PlaylistController::class, 'show']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/playlists', [PlaylistController::class, 'store']);
        Route::patch('/playlists/{playlist}', [PlaylistController::class, 'update']);
        Route::delete('/playlists/{playlist}', [PlaylistController::class, 'destroy']);
        Route::post('/playlists/{playlist}/videos', [PlaylistController::class, 'addVideo']);
        Route::delete('/playlists/{playlist}/videos/{video}', [PlaylistController::class, 'removeVideo']);
    });
});
