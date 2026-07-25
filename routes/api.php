<?php

use App\Http\Controllers\V1\Auth\ForgetPasswordController;
use App\Http\Controllers\V1\Auth\UserAuthController;
use App\Http\Controllers\V1\Auth\VerifyEmailController;
use App\Http\Controllers\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:20,1')->group(function () {

    Route::get('/', function () {
        return response()->json([
            'message' => 'Test API V1.0',
            'status' => 'OK - Servre works'
        ]);
    });

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

Route::post('uplod-on-azure', function (\App\Services\AzureBlobStorageService $azureService) {
    $file = request()->file('file');

    if (!$file) {
        return response()->json(['error' => 'No file provided'], 400);
    }

    $filePath = $azureService->uploadImage($file, 'myfiles');

    if (!$filePath) {
        return response()->json(['error' => 'Failed to upload file to Azure Blobs'], 500);
    }

    return response()->json([
        'file_path' => $filePath,
        'message' => 'File uploaded successfully to Azure Blobs',
    ]);
});
