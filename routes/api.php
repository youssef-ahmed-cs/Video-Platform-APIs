<?php

use App\Http\Controllers\V1\Auth\UserAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Auth\VerifyEmailController;
use App\Http\Controllers\V1\Auth\ForgetPasswordController;
use App\Http\Controllers\ProfileController;

Route::prefix('v1')->middleware('throttle:20,1')->group(function () {

    Route::middleware('throttle:3,1')->controller(VerifyEmailController::class)->group(function () {
        Route::post('/email/otp/send', 'sendOtp');
        Route::post('/email/otp/verify', 'verifyOtp');
    });

    Route::middleware('throttle:5,1')->controller(ForgetPasswordController::class)->group(function () {
        Route::post('/forgot-password', 'sendOtp');
        Route::post('/verify-password', 'verifyOtp');
        Route::post('/reset-password', 'resetPassword');
    });

    Route::post('register', [UserAuthController::class, 'register']);
    Route::post('login', [UserAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::controller(UserAuthController::class)->group(function () {
            Route::post('/logout', 'logout');
            Route::get('/me', 'user');
            Route::post('/refresh', 'refreshToken');
        });

        Route::controller(ProfileController::class)->group(function () {
            Route::get('/profile', 'show');
            Route::post('upload-avatar', 'uploadAvatarImage');
            Route::delete('delete-avatar', 'removeAvatarImage');
            Route::patch('update/profile', 'update');
            Route::delete('delete/profile', 'deleteProfilePermanently');
            Route::post('profile/change-password', 'updatePassword');
        });

    });
});
