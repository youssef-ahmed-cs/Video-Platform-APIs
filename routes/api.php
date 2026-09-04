<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:20,1')->group(function () {

    Route::get('/', function () {
        return response()->json([
           "message" => "Welcome to the API. Please refer to the documentation for available endpoints.",
        ]);
    });
});

Route::fallback(function () {
    return response()->json([
        'message' => "The requested resource was not found on this server.",
    ], 404);
});

Route::post('uplod-on-azure', function (\App\Services\AzureBlobStorageService $azureService) {
    $file = request()->file('file');

    if (!$file) {
        return response()->json(['error' => 'No file provided'], 400);
    }

    $filePath = $azureService->uploadImage($file, 'home');

    if (!$filePath) {
        return response()->json(['error' => 'Failed to upload file to Azure Blobs'], 500);
    }

    return response()->json([
        'file_path' => $filePath,
        'message' => 'File uploaded successfully to Azure Blobs',
    ]);
});

require __DIR__ . '/API/auth.php';
require __DIR__ . '/API/profile.php';
require __DIR__ . '/API/videos.php';
require __DIR__ . '/API/categories.php';
require __DIR__ . '/API/playlists.php';
require __DIR__ . '/API/podcasts.php';
