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

    public function test_admin_can_create_podcast_with_audio_and_cover(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create([
            'user_id' => $admin->id,
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        $audioFile = UploadedFile::fake()->create('episode1.mp3', 2048, 'audio/mpeg');
        $coverFile = UploadedFile::fake()->image('cover.jpg', 400, 400);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/podcasts', [
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
            'user_id' => $admin->id,
            'episode_number' => 1,
            'season_number' => 1,
        ]);

        $podcastId = $response->json('data.podcast.id');
        $this->assertDatabaseHas('category_podcast', [
            'podcast_id' => $podcastId,
            'category_id' => $category->id,
        ]);

        Notification::assertSentTo($admin, NewPodcastNotification::class);
    }

    public function test_regular_user_cannot_create_podcast(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/podcasts', [
            'title' => 'Unauthorized Podcast',
            'description' => 'A regular user attempting to create a podcast.',
            'is_public' => true,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('podcasts', [
            'title' => 'Unauthorized Podcast',
        ]);
    }

    public function test_guest_cannot_create_podcast(): void
    {
        $response = $this->postJson('/api/v1/podcasts', [
            'title' => 'Guest Podcast',
        ]);

        $response->assertStatus(401);
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

        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/podcasts', [
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
        $admin = User::factory()->create(['is_admin' => true]);
        $podcast = Podcast::create([
            'user_id' => $admin->id,
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
        $admin = User::factory()->create(['is_admin' => true]);
        $podcast = Podcast::create([
            'user_id' => $admin->id,
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

    public function test_regular_user_cannot_view_private_podcast(): void
    {
        $regularUser = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'Secret Podcast',
            'slug' => 'secret-podcast',
            'audio_url' => 'https://example.com/secret.mp3',
            'is_public' => false,
        ]);

        $this->actingAs($regularUser, 'sanctum')
            ->getJson('/api/v1/podcasts/'.$podcast->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This podcast is private.');
    }

    public function test_admin_can_view_private_podcast(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'My Private Podcast',
            'slug' => 'my-private-podcast',
            'audio_url' => 'https://example.com/private.mp3',
            'is_public' => false,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/podcasts/'.$podcast->id)
            ->assertStatus(200)
            ->assertJsonPath('data.podcast.title', 'My Private Podcast');
    }

    public function test_listen_endpoint_streams_audio_directly_without_redirecting_away(): void
    {
        Http::fake([
            'https://cdn.example.com/stream.mp3' => Http::response('fake-mp3-binary-content', 200, [
                'Content-Type' => 'audio/mpeg',
                'Content-Length' => '22',
            ]),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'Stream Podcast',
            'slug' => 'stream-podcast',
            'audio_url' => 'https://cdn.example.com/stream.mp3',
            'is_public' => true,
            'views' => 5,
        ]);

        $response = $this->get('/api/v1/podcasts/'.$podcast->id.'/listen');

        $response->assertStatus(200);
        $this->assertEquals('audio/mpeg', $response->headers->get('Content-Type'));
        $this->assertEquals('bytes', $response->headers->get('Accept-Ranges'));

        $this->assertDatabaseHas('podcasts', [
            'id' => $podcast->id,
            'views' => 6,
        ]);
    }

    public function test_slug_url_opens_podcast_player_page(): void
    {
        $admin = User::factory()->create(['name' => 'John Podcaster', 'is_admin' => true]);
        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'My Awesome Episode',
            'slug' => 'my-awesome-episode',
            'description' => 'A great discussion about tech.',
            'audio_url' => 'https://cdn.example.com/episode.mp3',
            'is_public' => true,
            'views' => 10,
        ]);

        $response = $this->get('/my-awesome-episode');

        $response->assertStatus(200);
        $response->assertSee('My Awesome Episode');
        $response->assertSee('John Podcaster');
        $response->assertSee('audio-player');
        $response->assertSee('/my-awesome-episode?stream=1');

        $this->assertDatabaseHas('podcasts', [
            'id' => $podcast->id,
            'views' => 11,
        ]);
    }

    public function test_slug_url_with_stream_param_streams_audio_directly(): void
    {
        Http::fake([
            'https://cdn.example.com/episode.mp3' => Http::response('direct-audio-bytes', 200, [
                'Content-Type' => 'audio/mpeg',
                'Content-Length' => '18',
            ]),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'Direct Stream Episode',
            'slug' => 'direct-stream-episode',
            'audio_url' => 'https://cdn.example.com/episode.mp3',
            'is_public' => true,
            'views' => 0,
        ]);

        $response = $this->get('/direct-stream-episode?stream=1');

        $response->assertStatus(200);
        $this->assertEquals('audio/mpeg', $response->headers->get('Content-Type'));

        $this->assertDatabaseHas('podcasts', [
            'id' => $podcast->id,
            'views' => 1,
        ]);
    }

    public function test_admin_can_update_podcast(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'Old Title',
            'slug' => 'old-title',
            'description' => 'Old Description',
            'is_public' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/podcasts/'.$podcast->id, [
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

    public function test_non_admin_cannot_update_or_delete_podcast(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);

        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'Admin Podcast',
            'slug' => 'admin-podcast',
            'is_public' => true,
        ]);

        $this->actingAs($regularUser, 'sanctum')
            ->patchJson('/api/v1/podcasts/'.$podcast->id, ['title' => 'Hacked'])
            ->assertStatus(403);

        $this->actingAs($regularUser, 'sanctum')
            ->deleteJson('/api/v1/podcasts/'.$podcast->id)
            ->assertStatus(403);
    }

    public function test_admin_can_delete_podcast_and_cleans_up_storage(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $audioFile = UploadedFile::fake()->create('ep.mp3', 1024, 'audio/mpeg');
        $uploadedAudioUrl = app(\App\Services\AzureBlobStorageService::class)->uploadAudio($audioFile, 'podcasts');

        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'Delete Me',
            'slug' => 'delete-me',
            'audio_path' => $uploadedAudioUrl,
            'audio_url' => $uploadedAudioUrl,
            'is_public' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/v1/podcasts/'.$podcast->id)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('podcasts', [
            'id' => $podcast->id,
        ]);
    }

    public function test_admin_can_upload_media_to_podcast(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'Pending Audio',
            'slug' => 'pending-audio',
            'is_public' => true,
        ]);

        $audio = UploadedFile::fake()->create('audio.mp3', 2048, 'audio/mpeg');
        $response = $this->actingAs($admin, 'sanctum')
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
        $coverResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/podcasts/'.$podcast->id.'/upload', [
                'file' => $cover,
            ]);

        $coverResponse->assertStatus(200)
            ->assertJsonPath('message', 'Cover image uploaded successfully.');

        $podcast->refresh();
        $this->assertNotNull($podcast->cover_image_url);
    }

    public function test_regular_user_cannot_upload_media_to_podcast(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);
        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'Pending Audio',
            'slug' => 'pending-audio',
            'is_public' => true,
        ]);

        $audio = UploadedFile::fake()->create('audio.mp3', 2048, 'audio/mpeg');
        $this->actingAs($regularUser, 'sanctum')
            ->postJson('/api/v1/podcasts/'.$podcast->id.'/upload', [
                'file' => $audio,
            ])
            ->assertStatus(403);
    }

    public function test_uploading_invalid_audio_type_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'Test Podcast',
            'slug' => 'test-podcast',
            'is_public' => true,
        ]);

        $invalidFile = UploadedFile::fake()->create('script.sh', 100, 'application/x-sh');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/podcasts/'.$podcast->id.'/upload', [
                'file' => $invalidFile,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_toggle_privacy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'Toggle Privacy',
            'slug' => 'toggle-privacy',
            'is_public' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/podcasts/'.$podcast->id.'/private')
            ->assertStatus(200)
            ->assertJsonPath('data.podcast.is_public', false);

        $this->assertDatabaseHas('podcasts', [
            'id' => $podcast->id,
            'is_public' => false,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/podcasts/'.$podcast->id.'/public')
            ->assertStatus(200)
            ->assertJsonPath('data.podcast.is_public', true);

        $this->assertDatabaseHas('podcasts', [
            'id' => $podcast->id,
            'is_public' => true,
        ]);
    }

    public function test_regular_user_cannot_toggle_privacy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);
        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'Toggle Privacy',
            'slug' => 'toggle-privacy',
            'is_public' => true,
        ]);

        $this->actingAs($regularUser, 'sanctum')
            ->patchJson('/api/v1/podcasts/'.$podcast->id.'/private')
            ->assertStatus(403);

        $this->actingAs($regularUser, 'sanctum')
            ->patchJson('/api/v1/podcasts/'.$podcast->id.'/public')
            ->assertStatus(403);
    }

    public function test_admin_can_retrieve_my_podcasts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        Podcast::create([
            'user_id' => $admin->id,
            'title' => 'My First Podcast',
            'slug' => 'my-first-podcast',
            'is_public' => true,
        ]);

        Podcast::create([
            'user_id' => $admin->id,
            'title' => 'My Draft Podcast',
            'slug' => 'my-draft-podcast',
            'is_public' => false,
        ]);

        Podcast::create([
            'user_id' => $otherAdmin->id,
            'title' => 'Someone Elses Podcast',
            'slug' => 'someone-elses-podcast',
            'is_public' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/my-podcasts');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.podcasts')
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_search_podcasts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Podcast::create([
            'user_id' => $admin->id,
            'title' => 'Artificial Intelligence Deep Dive',
            'slug' => 'ai-deep-dive',
            'description' => 'Exploring neural networks',
            'is_public' => true,
        ]);

        Podcast::create([
            'user_id' => $admin->id,
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

    public function test_admin_can_add_and_remove_podcast_to_playlist(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $playlist = Playlist::create([
            'user_id' => $admin->id,
            'name' => 'Podcast Playlist',
            'slug' => 'podcast-playlist',
            'is_public' => true,
        ]);

        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'Standalone Podcast',
            'slug' => 'standalone-podcast',
            'is_public' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/playlists/'.$playlist->id.'/podcasts', [
                'podcast_id' => $podcast->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('message', 'Podcast added to playlist.');

        $podcast->refresh();
        $this->assertEquals($playlist->id, $podcast->playlist_id);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/v1/playlists/'.$playlist->id.'/podcasts/'.$podcast->id)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Podcast removed from playlist.');

        $podcast->refresh();
        $this->assertNull($podcast->playlist_id);
    }

    public function test_regular_user_cannot_add_or_remove_podcast_to_playlist(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);
        $playlist = Playlist::create([
            'user_id' => $regularUser->id,
            'name' => 'User Playlist',
            'slug' => 'user-playlist',
            'is_public' => true,
        ]);

        $podcast = Podcast::create([
            'user_id' => $admin->id,
            'title' => 'Admin Podcast',
            'slug' => 'admin-podcast',
            'is_public' => true,
        ]);

        $this->actingAs($regularUser, 'sanctum')
            ->postJson('/api/v1/playlists/'.$playlist->id.'/podcasts', [
                'podcast_id' => $podcast->id,
            ])
            ->assertStatus(403);

        $this->actingAs($regularUser, 'sanctum')
            ->deleteJson('/api/v1/playlists/'.$playlist->id.'/podcasts/'.$podcast->id)
            ->assertStatus(403);
    }
}
