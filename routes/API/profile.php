<?php

use App\Http\Controllers\V1\Auth\UserAuthController;
use App\Http\Controllers\V1\Auth\VerifyEmailController;
use App\Http\Controllers\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:20,1')->group(function () {
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::controller(ProfileController::class)->group(function () {
            Route::get('/profile', 'show');
            Route::post('/profile/upload-avatar', 'uploadAvatarImage');
            Route::delete('/profile/delete-avatar', 'removeAvatarImage');
            Route::patch('/profile', 'update');
            Route::delete('/profile/force-delete', 'deleteProfilePermanently');
            Route::delete('/profile/soft-delete', 'softDeleteProfile');
            Route::post('/profile/{id}/restore', 'restore');
            Route::post('/profile/change-password', 'updatePassword');
        });
    });
});
