<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoPlatformApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_watch_and_delete_a_video(): void
    {
        config()->set('filesystems.video_disk', 'azure');
        Storage::fake('azure');

        $user = User::factory()->create();
        $category = Category::create([
            'user_id' => $user->id,
            'name' => 'Movies',
            'slug' => 'movies',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/videos', [
            'title' => 'Demo Movie',
            'description' => 'A short demo movie.',
            'video' => UploadedFile::fake()->create('demo.mp4', 1024, 'video/mp4'),
            'thumbnail' => UploadedFile::fake()->image('cover.jpg', 200, 200),
            'category_ids' => [$category->id],
            'is_public' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('video.title', 'Demo Movie')
            ->assertJsonPath('video.is_public', true);

        $this->assertDatabaseHas('videos', [
            'title' => 'Demo Movie',
            'user_id' => $user->id,
        ]);

        $videoId = $response->json('video.id');

        $this->getJson('/api/v1/videos/'.$videoId.'/watch')->assertStatus(200);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/videos/'.$videoId)->assertStatus(200);
    }

    public function test_user_can_create_categories_and_playlist_with_videos(): void
    {
        config()->set('filesystems.video_disk', 'azure');
        Storage::fake('azure');

        $user = User::factory()->create();

        $videoResponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/videos', [
            'title' => 'Playlist Sample',
            'video' => UploadedFile::fake()->create('sample.mp4', 1024, 'video/mp4'),
        ]);

        $videoResponse->assertStatus(201);
        $videoId = $videoResponse->json('video.id');

        $categoryResponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/categories', [
            'name' => 'Comedy',
            'description' => 'Humorous videos',
        ]);

        $categoryResponse->assertStatus(201)
            ->assertJsonPath('category.name', 'Comedy');

        $playlistResponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/playlists', [
            'name' => 'My Favourites',
            'description' => 'Favourite videos',
            'is_public' => true,
        ]);

        $playlistResponse->assertStatus(201)
            ->assertJsonPath('playlist.name', 'My Favourites');

        $playlistId = $playlistResponse->json('playlist.id');

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/playlists/'.$playlistId.'/videos', [
            'video_id' => $videoId,
        ])->assertStatus(200);

        $this->assertDatabaseHas('playlist_video', [
            'playlist_id' => $playlistId,
            'video_id' => $videoId,
        ]);
    }
}
