<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AzureBlobStorageService
{
     public function uploadImage($file, $path)
    {
        $fileName = uniqid() . Str::random(5) . time() . '.' . $file->getClientOriginalExtension();
        $contentType = $file->getClientMimeType();
        $options = ['Content-Type' => $contentType];

        Storage::disk('azure')->putFileAs($path, $file, $fileName, $options);

        return Storage::disk('azure')->url("$path/$fileName");
    }
}
