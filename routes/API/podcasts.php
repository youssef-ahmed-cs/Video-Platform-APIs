<?php

use App\Http\Controllers\V1\PodcastController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/podcasts', [PodcastController::class, 'index']);
    Route::get('/podcasts/{podcast}', [PodcastController::class, 'show']);
    Route::get('/search/podcasts', [PodcastController::class, 'search']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/podcasts', [PodcastController::class, 'store']);
        Route::patch('/podcasts/{podcast}', [PodcastController::class, 'update']);
        Route::delete('/podcasts/{podcast}', [PodcastController::class, 'destroy']);
        Route::post('/podcasts/{podcast}/upload', [PodcastController::class, 'upload']);
    });
});
