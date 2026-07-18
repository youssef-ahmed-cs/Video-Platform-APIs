<?php

namespace Tests\Unit\Services;

use App\Services\HackCDNStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HackCDNStorageTest extends TestCase
{
    public function test_it_uploads_a_file_and_returns_the_public_url(): void
    {
        config()->set('services.hackcdn.key', 'test-key');
        config()->set('services.hackcdn.host', 'https://cdn.hackclub.com');

        Http::fake([
            'https://cdn.hackclub.com/api/v4/upload' => Http::response([
                'url' => 'https://cdn.hackclub.com/abc123/photo.jpg',
            ], 200),
        ]);

        $file = new UploadedFile(
            tempnam(sys_get_temp_dir(), 'hackcdn'),
            'photo.jpg',
            'image/jpeg',
            null,
            true
        );

        $service = new HackCDNStorage();
        $url = $service->uploadImage($file, 'avatar', 'Youssef Ahmed');

        $this->assertSame('https://cdn.hackclub.com/abc123/photo.jpg', $url);

        Http::assertSent(function ($request): bool {
            $body = (string) $request->body();

            return $request->url() === 'https://cdn.hackclub.com/api/v4/upload'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && str_contains($body, 'name="file"')
                && str_contains($body, 'filename="youssef_');
        });
    }

    public function test_it_extracts_the_upload_id_from_a_full_url_before_deleting(): void
    {
        config()->set('services.hackcdn.key', 'test-key');
        config()->set('services.hackcdn.host', 'https://cdn.hackclub.com');

        Http::fake([
            'https://cdn.hackclub.com/api/v4/upload/01234567-89ab-cdef-0123-456789abcdef' => Http::response([
                'id' => '01234567-89ab-cdef-0123-456789abcdef',
                'deleted' => true,
            ], 200),
        ]);

        $service = new HackCDNStorage();
        $result = $service->deleteUpload('https://cdn.hackclub.com/01234567-89ab-cdef-0123-456789abcdef/photo.jpg');

        $this->assertTrue($result['deleted']);
        $this->assertSame('01234567-89ab-cdef-0123-456789abcdef', $result['id']);
    }
}
