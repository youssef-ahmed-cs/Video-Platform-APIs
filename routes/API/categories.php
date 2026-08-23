<?php

use App\Http\Controllers\V1\CategoryController;
use App\Http\Controllers\V1\PlaylistController;
use App\Http\Controllers\V1\VideoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/search/categories', [CategoryController::class, 'search']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::patch('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    });
});
