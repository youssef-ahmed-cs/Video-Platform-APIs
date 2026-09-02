<?php

use App\Http\Controllers\V1\Auth\ForgetPasswordController;
use App\Http\Controllers\V1\Auth\RestoreDeletedAccountController;
use App\Http\Controllers\V1\Auth\UserAuthController;
use App\Http\Controllers\V1\Auth\VerifyEmailController;
use App\Http\Controllers\V1\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Mail\InvoicePaidMail;

Route::prefix('v1')->middleware('throttle:20,1')->group(function () {

    Route::middleware('throttle:10,1')->controller(VerifyEmailController::class)->group(function () {
        Route::post('/email/otp/send', 'sendOtp');
        Route::post('/email/otp/verify', 'verifyOtp');
    });

    Route::middleware('throttle:10,1')->controller(ForgetPasswordController::class)->group(function () {
        Route::post('/forgot-password', 'sendOtp');
        Route::post('/verify-password', 'verifyOtp');
        Route::post('/reset-password', 'resetPassword');
    });

    Route::middleware('throttle:10,1')->controller(RestoreDeletedAccountController::class)->group(function () {
        Route::post('/restore-account/send-otp', 'sendOtp');
        Route::post('/restore-account/verify-otp', 'verifyOtpAndRestore');
        Route::post('/restore-account/request-admin-activation', 'requestAdminActivation');
    });

    Route::post('register', [UserAuthController::class, 'register']);
    Route::post('login', [UserAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {

        Route::controller(UserAuthController::class)->group(function () {
            Route::post('/logout', 'logout');
            Route::get('/me', 'user');
            Route::post('/refresh', 'refreshToken');
        });

    });
});
