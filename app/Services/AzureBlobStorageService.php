<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AzureBlobStorageService
{
    protected function diskName(): string
    {
        return config('filesystems.podcast_disk', config('filesystems.video_disk', 'azure'));
    }

    public function uploadImage($file, $path = 'images'): ?string
    {
        if (!$file) {
            return null;
        }

        $extension = method_exists($file, 'getClientOriginalExtension')
            ? $file->getClientOriginalExtension()
            : pathinfo($file->getFilename(), PATHINFO_EXTENSION);

        $fileName = uniqid() . Str::random(5) . time() . '.' . ($extension ?: 'jpg');
        $contentType = method_exists($file, 'getClientMimeType') ? $file->getClientMimeType() : 'image/jpeg';
        $options = ['Content-Type' => $contentType];

        $disk = $this->diskName();
        Storage::disk($disk)->putFileAs($path, $file, $fileName, $options);

        return Storage::disk($disk)->url("$path/$fileName");
    }

    public function uploadAudio($file, string $path = 'podcasts'): ?string
    {
        if (!$file) {
            return null;
        }

        $extension = method_exists($file, 'getClientOriginalExtension')
            ? $file->getClientOriginalExtension()
            : pathinfo($file->getFilename(), PATHINFO_EXTENSION);

        $fileName = uniqid() . Str::random(5) . time() . '.' . ($extension ?: 'mp3');
        $contentType = method_exists($file, 'getClientMimeType')
            ? ($file->getClientMimeType() ?: 'audio/mpeg')
            : 'audio/mpeg';

        $options = ['Content-Type' => $contentType];

        $disk = $this->diskName();
        Storage::disk($disk)->putFileAs($path, $file, $fileName, $options);

        return Storage::disk($disk)->url("$path/$fileName");
    }

    public function deleteFile(?string $urlOrPath, string $path = 'podcasts'): bool
    {
        if (empty($urlOrPath)) {
            return false;
        }

        $disk = $this->diskName();
        $filePath = $urlOrPath;

        if (str_contains($urlOrPath, '://')) {
            $parsed = parse_url($urlOrPath, PHP_URL_PATH);
            $filePath = ltrim((string) $parsed, '/');

            $pos = strpos($filePath, $path . '/');
            if ($pos !== false) {
                $filePath = substr($filePath, $pos);
            }
        } else {
            $filePath = ltrim($filePath, '/');
        }

        try {
            if (Storage::disk($disk)->exists($filePath)) {
                return Storage::disk($disk)->delete($filePath);
            }
        } catch (\Throwable $e) {
            // Log or ignore on storage driver mismatch
        }

        return false;
    }
}
