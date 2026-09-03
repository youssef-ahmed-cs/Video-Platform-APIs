<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Playlist;
use App\Models\Podcast;
use App\Models\User;
use App\Notifications\NewPodcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PodcastApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.podcast_disk', 'azure');
        config()->set('filesystems.video_disk', 'azure');
        Storage::fake('azure');

        Http::fake([
            'https://ai.hackclub.com/proxy/v1/moderations' => Http::response([
                'results' => [[
                    'flagged' => false,
                    'categories' => [],
                    'category_scores' => [],
                ]],
            ], 200),
        ]);
    }

    public function test_authenticated_user_can_create_podcast_with_audio_and_cover(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $category = Category::create([
            'user_id' => $user->id,
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        $audioFile = UploadedFile::fake()->create('episode1.mp3', 2048, 'audio/mpeg');
        $coverFile = UploadedFile::fake()->image('cover.jpg', 400, 400);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/podcasts', [
            'title' => 'Tech Talk Episode 1',
            'description' => 'Discussion on modern software architecture.',
            'audio' => $audioFile,
            'cover_image' => $coverFile,
            'category_ids' => [$category->id],
            'episode_number' => 1,
            'season_number' => 1,
            'duration' => 1800,
            'is_public' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.podcast.title', 'Tech Talk Episode 1')
            ->assertJsonPath('data.podcast.episode_number', 1)
            ->assertJsonPath('data.podcast.season_number', 1)
            ->assertJsonPath('data.podcast.is_public', true);

        $this->assertDatabaseHas('podcasts', [
            'title' => 'Tech Talk Episode 1',
            'user_id' => $user->id,
            'episode_number' => 1,
            'season_number' => 1,
        ]);

        $podcastId = $response->json('data.podcast.id');
        $this->assertDatabaseHas('category_podcast', [
            'podcast_id' => $podcastId,
            'category_id' => $category->id,
        ]);

        Notification::assertSentTo($user, NewPodcastNotification::class);
    }

    public function test_podcast_creation_is_rejected_when_content_violates_moderation(): void
    {
        Http::fake([
            'https://ai.hackclub.com/proxy/v1/moderations' => Http::response([
                'results' => [[
                    'flagged' => true,
                    'categories' => ['hate' => true],
                    'category_scores' => ['hate' => 0.99],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/podcasts', [
            'title' => 'Flagged Title',
            'description' => 'Flagged description',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('field', 'title');

        $this->assertDatabaseMissing('podcasts', [
            'title' => 'Flagged Title',
        ]);
    }

    public function test_show_podcast_increments_views(): void
    {
        $user = User::factory()->create();
        $podcast = Podcast::create([
            'user_id' => $user->id,
            'title' => 'Listen Now',
            'slug' => 'listen-now',
            'audio_url' => 'https://example.com/audio.mp3',
            'is_public' => true,
            'views' => 0,
        ]);

        $response = $this->getJson('/api/v1/podcasts/'.$podcast->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.podcast.title', 'Listen Now');

        $this->assertDatabaseHas('podcasts', [
            'id' => $podcast->id,
            'views' => 1,
        ]);
    }

    public function test_guest_cannot_view_private_podcast(): void
    {
        $user = User::factory()->create();
        $podcast = Podcast::create([
            'user_id' => $user->id,
            'title' => 'Secret Podcast',
            'slug' => 'secret-podcast',
            'audio_url' => 'https://example.com/secret.mp3',
            'is_public' => false,
        ]);

        $this->getJson('/api/v1/podcasts/'.$podcast->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This podcast is private.');
    }

    public function test_owner_can_view_private_podcast(): void
    {
        $user = User::factory()->create();
        $podcast = Podcast::create([
            'user_id' => $user->id,
            'title' => 'My Private Podcast',
            'slug' => 'my-private-podcast',
            'audio_url' => 'https://example.com/private.mp3',
            'is_public' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/podcasts/'.$podcast->id)
            ->assertStatus(200)
            ->assertJsonPath('data.podcast.title', 'My Private Podcast');
    }

    public function test_listen_endpoint_redirects_to_audio_url_and_increments_views(): void
    {
        $user = User::factory()->create();
        $podcast = Podcast::create([
            'user_id' => $user->id,
            'title' => 'Stream Podcast',
            'slug' => 'stream-podcast',
            'audio_url' => 'https://cdn.example.com/stream.mp3',
            'is_public' => true,
            'views' => 5,
        ]);

        $response = $this->getJson('/api/v1/podcasts/'.$podcast->id.'/listen');

        $response->assertStatus(302);
        $response->assertRedirect('https://cdn.example.com/stream.mp3');

        $this->assertDatabaseHas('podcasts', [
            'id' => $podcast->id,
            'views' => 6,
        ]);
    }

    public function test_owner_can_update_podcast(): void
    {
        $user = User::factory()->create();
        $podcast = Podcast::create([
            'user_id' => $user->id,
            'title' => 'Old Title',
            'slug' => 'old-title',
            'description' => 'Old Description',
            'is_public' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->patchJson('/api/v1/podcasts/'.$podcast->id, [
            'title' => 'New Title',
            'description' => 'Updated Description',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.podcast.title', 'New Title')
            ->assertJsonPath('data.podcast.description', 'Updated Description');

        $this->assertDatabaseHas('podcasts', [
            'id' => $podcast->id,
            'title' => 'New Title',
            'slug' => 'new-title',
        ]);
    }

    public function test_non_owner_cannot_update_or_delete_podcast(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $podcast = Podcast::create([
            'user_id' => $owner->id,
            'title' => 'Owner Podcast',
            'slug' => 'owner-podcast',
            'is_public' => true,
        ]);

        $this->actingAs($stranger, 'sanctum')
            ->patchJson('/api/v1/podcasts/'.$podcast->id, ['title' => 'Hacked'])
            ->assertStatus(403);

        $this->actingAs($stranger, 'sanctum')
            ->deleteJson('/api/v1/podcasts/'.$podcast->id)
            ->assertStatus(403);
    }

    public function test_owner_can_delete_podcast_and_cleans_up_storage(): void
    {
        $user = User::factory()->create();
        $audioFile = UploadedFile::fake()->create('ep.mp3', 1024, 'audio/mpeg');
        $uploadedAudioUrl = app(\App\Services\AzureBlobStorageService::class)->uploadAudio($audioFile, 'podcasts');

        $podcast = Podcast::create([
            'user_id' => $user->id,
            'title' => 'Delete Me',
            'slug' => 'delete-me',
            'audio_path' => $uploadedAudioUrl,
            'audio_url' => $uploadedAudioUrl,
            'is_public' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/podcasts/'.$podcast->id)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('podcasts', [
            'id' => $podcast->id,
        ]);
    }

    public function test_owner_can_upload_media_to_podcast(): void
    {
        $user = User::factory()->create();
        $podcast = Podcast::create([
            'user_id' => $user->id,
            'title' => 'Pending Audio',
            'slug' => 'pending-audio',
            'is_public' => true,
        ]);

        $audio = UploadedFile::fake()->create('audio.mp3', 2048, 'audio/mpeg');
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/podcasts/'.$podcast->id.'/upload', [
                'file' => $audio,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Audio uploaded successfully.');

        $podcast->refresh();
        $this->assertNotNull($podcast->audio_url);
        $this->assertEquals(2048 * 1024, $podcast->size);

        // Test uploading cover image
        $cover = UploadedFile::fake()->image('cover.jpg', 300, 300);
        $coverResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/podcasts/'.$podcast->id.'/upload', [
                'file' => $cover,
            ]);

        $coverResponse->assertStatus(200)
            ->assertJsonPath('message', 'Cover image uploaded successfully.');

        $podcast->refresh();
        $this->assertNotNull($podcast->cover_image_url);
    }

    public function test_uploading_invalid_audio_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $podcast = Podcast::create([
            'user_id' => $user->id,
            'title' => 'Test Podcast',
            'slug' => 'test-podcast',
            'is_public' => true,
        ]);

        $invalidFile = UploadedFile::fake()->create('script.sh', 100, 'application/x-sh');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/podcasts/'.$podcast->id.'/upload', [
                'file' => $invalidFile,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_owner_can_toggle_privacy(): void
    {
        $user = User::factory()->create();
        $podcast = Podcast::create([
            'user_id' => $user->id,
            'title' => 'Toggle Privacy',
            'slug' => 'toggle-privacy',
            'is_public' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/podcasts/'.$podcast->id.'/private')
            ->assertStatus(200)
            ->assertJsonPath('data.podcast.is_public', false);

        $this->assertDatabaseHas('podcasts', [
            'id' => $podcast->id,
            'is_public' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/podcasts/'.$podcast->id.'/public')
            ->assertStatus(200)
            ->assertJsonPath('data.podcast.is_public', true);

        $this->assertDatabaseHas('podcasts', [
            'id' => $podcast->id,
            'is_public' => true,
        ]);
    }

    public function test_user_can_retrieve_my_podcasts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Podcast::create([
            'user_id' => $user->id,
            'title' => 'My First Podcast',
            'slug' => 'my-first-podcast',
            'is_public' => true,
        ]);

        Podcast::create([
            'user_id' => $user->id,
            'title' => 'My Draft Podcast',
            'slug' => 'my-draft-podcast',
            'is_public' => false,
        ]);

        Podcast::create([
            'user_id' => $otherUser->id,
            'title' => 'Someone Elses Podcast',
            'slug' => 'someone-elses-podcast',
            'is_public' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/my-podcasts');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.podcasts')
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_search_podcasts(): void
    {
        $user = User::factory()->create();

        Podcast::create([
            'user_id' => $user->id,
            'title' => 'Artificial Intelligence Deep Dive',
            'slug' => 'ai-deep-dive',
            'description' => 'Exploring neural networks',
            'is_public' => true,
        ]);

        Podcast::create([
            'user_id' => $user->id,
            'title' => 'Gardening Basics',
            'slug' => 'gardening-basics',
            'description' => 'How to plant tomatoes',
            'is_public' => true,
        ]);

        $response = $this->getJson('/api/v1/search/podcasts?query=Artificial');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.podcasts')
            ->assertJsonPath('data.podcasts.0.title', 'Artificial Intelligence Deep Dive');
    }

    public function test_user_can_add_and_remove_podcast_to_playlist(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::create([
            'user_id' => $user->id,
            'name' => 'Podcast Playlist',
            'slug' => 'podcast-playlist',
            'is_public' => true,
        ]);

        $podcast = Podcast::create([
            'user_id' => $user->id,
            'title' => 'Standalone Podcast',
            'slug' => 'standalone-podcast',
            'is_public' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/playlists/'.$playlist->id.'/podcasts', [
                'podcast_id' => $podcast->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('message', 'Podcast added to playlist.');

        $podcast->refresh();
        $this->assertEquals($playlist->id, $podcast->playlist_id);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/playlists/'.$playlist->id.'/podcasts/'.$podcast->id)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Podcast removed from playlist.');

        $podcast->refresh();
        $this->assertNull($podcast->playlist_id);
    }
}
