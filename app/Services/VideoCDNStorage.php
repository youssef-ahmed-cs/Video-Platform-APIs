<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VideoCDNStorage
{
    public function uploadVideo($file, $path, ?string $userName = null): string
    {
        return $this->uploadFile($file, $path, $userName);
    }

    public function uploadImage($file, $path, ?string $userName = null): string
    {
        return $this->uploadFile($file, $path, $userName);
    }

    public function uploadVideoFromUrl(string $sourceUrl, ?string $userName = null): string
    {
        $response = Http::withHeaders($this->headers())
            ->post($this->endpoint('upload_from_url'), [
                'url' => $sourceUrl,
                'filename' => $this->buildFileNameFromUrl($sourceUrl, $userName),
            ]);

        if (!$response->successful()) {
            throw new RuntimeException($this->messageFromResponse($response), $response->status());
        }

        $url = $response->json('url');

        if (!is_string($url) || $url === '') {
            Log::error('VideoCDNStorage: Invalid response from Hack Club CDN', [
                'response' => $response->json(),
            ]);
            throw new RuntimeException('Hack Club CDN did not return a valid public URL.');
        }

        return $url;
    }

    private function uploadFile($file, $path, ?string $userName = null): string
    {
        if (!$file instanceof UploadedFile) {
            throw new RuntimeException('A valid uploaded file is required.');
        }

        $response = Http::withHeaders($this->headers())
            ->attach(
                'file',
                fopen($file->getRealPath(), 'r'),
                $this->buildFileName($file, $userName),
                [
                    'Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
                ]
            )
            ->post($this->endpoint('upload'));

        if (!$response->successful()) {
            throw new RuntimeException($this->messageFromResponse($response), $response->status());
        }

        $url = $response->json('url');

        if (!is_string($url) || $url === '') {
            Log::error('VideoCDNStorage: Invalid response from Hack Club CDN', [
                'response' => $response->json(),
            ]);
            throw new RuntimeException('Hack Club CDN did not return a valid public URL.');
        }

        return $url;
    }

    public function deleteUpload(string $value): array
    {
        $uploadId = $this->extractUploadId($value);

        if ($uploadId === '') {
            return ['id' => null, 'deleted' => true, 'skipped' => true];
        }

        $response = Http::withHeaders($this->headers())
            ->delete($this->endpoint('upload/' . $uploadId));

        if ($response->successful()) {
            return $response->json();
        }

        if ($response->status() === 404) {
            return ['id' => $uploadId, 'deleted' => true, 'skipped' => true, 'reason' => 'not_found'];
        }

        throw new RuntimeException($this->messageFromResponse($response), $response->status());
    }

    private function buildFileName(UploadedFile $file, ?string $userName = null): string
    {
        $firstName = $this->normalizeFirstName($userName);
        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $suffix = str_pad((string)random_int(10000, 99999), 5, '0', STR_PAD_LEFT);

        if ($extension !== '') {
            return $firstName . '_' . $suffix . '.' . $extension;
        }

        return $firstName . '_' . $suffix;
    }

    private function buildFileNameFromUrl(string $sourceUrl, ?string $userName = null): string
    {
        $firstName = $this->normalizeFirstName($userName);
        $path = parse_url($sourceUrl, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        $suffix = str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);

        if ($extension !== '') {
            return $firstName . '_' . $suffix . '.' . $extension;
        }

        return $firstName . '_' . $suffix;
    }

    private function normalizeFirstName(?string $userName): string
    {
        $name = trim((string)$userName);

        if ($name === '') {
            return 'user';
        }

        $firstName = trim(explode(' ', $name)[0]);
        $firstName = preg_replace('/[^A-Za-z0-9]+/', '', $firstName);

        if ($firstName === '') {
            return 'user';
        }

        return strtolower($firstName);
    }

    private function extractUploadId(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (str_contains($value, '://')) {
            $parsed = parse_url($value);
            $path = isset($parsed['path']) ? trim((string) $parsed['path'], '/') : '';
            $segments = array_values(array_filter(explode('/', $path), static fn ($segment) => $segment !== ''));

            if (count($segments) > 0) {
                return $segments[0];
            }
        }

        return $value;
    }

    private function headers(?string $downloadAuthorization = null): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey(),
        ];

        return $headers;
    }

    private function endpoint(string $path): string
    {
        $baseUrl = rtrim($this->baseUrl(), '/');

        return $baseUrl . '/api/v4/' . $path;
    }

    private function baseUrl(): string
    {
        $configured = trim((string)config('services.hackcdn.host', config('filesystems.disks.hackcdn.url', 'https://cdn.hackclub.com')));

        if ($configured === '') {
            return 'https://cdn.hackclub.com';
        }

        if (preg_match('#/api/v4/(upload|upload_from_url|me)$#', $configured)) {
            return preg_replace('#/api/v4/(upload|upload_from_url|me)$#', '', $configured);
        }

        return rtrim($configured, '/');
    }

    private function apiKey(): string
    {
        $key = trim((string)config('services.hackcdn.key'));

        if ($key === '') {
            Log::error('VideoCDNStorage: API key is not configured');
        }

        return $key;
    }

    private function messageFromResponse($response): string
    {
        $error = $response->json('error');

        if (is_string($error) && $error !== '') {
            return $error;
        }

        return 'Video Club CDN request failed.';
    }
}
