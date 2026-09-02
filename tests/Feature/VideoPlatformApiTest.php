<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use App\Notifications\NewVideoNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoPlatformApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_upload_watch_and_delete_a_video(): void
    {
        config()->set('filesystems.video_disk', 'azure');
        config()->set('services.hackcdn.key', 'test-key');
        config()->set('services.hackcdn.host', 'https://cdn.hackclub.com');
        Storage::fake('azure');
        Http::fake([
            'https://cdn.hackclub.com/api/v4/upload' => Http::response([
                'url' => 'https://cdn.hackclub.com/video-upload-1/file.mp4',
            ], 200),
            'https://cdn.hackclub.com/api/v4/upload/*' => Http::response([
                'deleted' => true,
            ], 200),
            'https://ai.hackclub.com/proxy/v1/moderations' => Http::response([
                'results' => [[
                    'flagged' => false,
                    'categories' => [],
                    'category_scores' => [],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create(['is_admin' => true]);
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
            ->assertJsonPath('success', true)
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

    public function test_admin_can_create_categories_and_playlist_with_videos(): void
    {
        config()->set('filesystems.video_disk', 'azure');
        config()->set('services.hackcdn.key', 'test-key');
        config()->set('services.hackcdn.host', 'https://cdn.hackclub.com');
        Storage::fake('azure');
        Http::fake([
            'https://cdn.hackclub.com/api/v4/upload' => Http::response([
                'url' => 'https://cdn.hackclub.com/video-upload-2/file.mp4',
            ], 200),
            'https://ai.hackclub.com/proxy/v1/moderations' => Http::response([
                'results' => [[
                    'flagged' => false,
                    'categories' => [],
                    'category_scores' => [],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create(['is_admin' => true]);

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

    public function test_non_admin_cannot_manage_videos_or_categories(): void
    {
        config()->set('filesystems.video_disk', 'azure');
        Storage::fake('azure');

        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/videos', [
                'title' => 'Blocked upload',
                'video' => UploadedFile::fake()->create('blocked.mp4', 1024, 'video/mp4'),
            ])
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/categories', [
                'name' => 'Blocked category',
            ])
            ->assertStatus(403);
    }

    public function test_admin_can_upload_video_from_url(): void
    {
        config()->set('filesystems.video_disk', 'azure');
        config()->set('services.hackcdn.key', 'test-key');
        config()->set('services.hackcdn.host', 'https://cdn.hackclub.com');
        Storage::fake('azure');
        Http::fake([
            'https://cdn.hackclub.com/api/v4/upload_from_url' => Http::response([
                'url' => 'https://cdn.hackclub.com/video-from-url-1/remote.mp4',
            ], 200),
            'https://ai.hackclub.com/proxy/v1/moderations' => Http::response([
                'results' => [[
                    'flagged' => false,
                    'categories' => [],
                    'category_scores' => [],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/videos', [
            'title' => 'Remote Video',
            'video_url' => 'https://example.com/media/remote.mp4',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('video.title', 'Remote Video');

        $this->assertDatabaseHas('videos', [
            'title' => 'Remote Video',
            'user_id' => $user->id,
            'video_url' => 'https://cdn.hackclub.com/video-from-url-1/remote.mp4',
        ]);
    }

    public function test_new_video_notifies_all_users(): void
    {
        config()->set('filesystems.video_disk', 'azure');
        config()->set('services.hackcdn.key', 'test-key');
        config()->set('services.hackcdn.host', 'https://cdn.hackclub.com');
        Storage::fake('azure');
        Http::fake([
            'https://cdn.hackclub.com/api/v4/upload' => Http::response([
                'url' => 'https://cdn.hackclub.com/video-upload-notify/file.mp4',
            ], 200),
            'https://ai.hackclub.com/proxy/v1/moderations' => Http::response([
                'results' => [[
                    'flagged' => false,
                    'categories' => [],
                    'category_scores' => [],
                ]],
            ], 200),
        ]);
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $userA = User::factory()->create(['is_admin' => false]);
        $userB = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/videos', [
            'title' => 'Notify Video',
            'video' => UploadedFile::fake()->create('notify.mp4', 1024, 'video/mp4'),
        ])->assertStatus(201);

        Notification::assertSentTo($admin, NewVideoNotification::class);
        Notification::assertSentTo($userA, NewVideoNotification::class);
        Notification::assertSentTo($userB, NewVideoNotification::class);
    }

    public function test_authenticated_user_can_comment_on_public_video(): void
    {
        Http::fake([
            'https://ai.hackclub.com/proxy/v1/moderations' => Http::response([
                'results' => [[
                    'flagged' => false,
                    'categories' => [],
                    'category_scores' => [],
                ]],
            ], 200),
        ]);

        $owner = User::factory()->create(['is_admin' => true]);
        $commenter = User::factory()->create(['is_admin' => false]);

        $video = Video::create([
            'user_id' => $owner->id,
            'title' => 'Commentable Video',
            'slug' => 'commentable-video',
            'description' => 'Sample',
            'video_path' => 'https://cdn.hackclub.com/sample/video.mp4',
            'video_url' => 'https://cdn.hackclub.com/sample/video.mp4',
            'is_public' => true,
        ]);

        $response = $this->actingAs($commenter, 'sanctum')
            ->postJson('/api/v1/videos/'.$video->id.'/comments', [
                'body' => 'Great video!',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('comment.body', 'Great video!')
            ->assertJsonPath('comment.video_id', $video->id);

        $this->assertDatabaseHas('comments', [
            'video_id' => $video->id,
            'user_id' => $commenter->id,
            'body' => 'Great video!',
        ]);
    }

    public function test_video_upload_is_rejected_when_title_or_description_is_flagged(): void
    {
        config()->set('filesystems.video_disk', 'azure');
        config()->set('services.hackcdn.key', 'test-key');
        config()->set('services.hackcdn.host', 'https://cdn.hackclub.com');
        Storage::fake('azure');

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

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/videos', [
            'title' => 'Flagged Title',
            'description' => 'Should be rejected by moderation.',
            'video' => UploadedFile::fake()->create('flagged.mp4', 1024, 'video/mp4'),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('field', 'title');

        $this->assertDatabaseMissing('videos', [
            'title' => 'Flagged Title',
            'user_id' => $admin->id,
        ]);
    }

    public function test_comment_is_rejected_when_flagged_by_moderation(): void
    {
        Http::fake([
            'https://ai.hackclub.com/proxy/v1/moderations' => Http::response([
                'results' => [[
                    'flagged' => true,
                    'categories' => ['harassment' => true],
                    'category_scores' => ['harassment' => 0.95],
                ]],
            ], 200),
        ]);

        $owner = User::factory()->create(['is_admin' => true]);
        $commenter = User::factory()->create(['is_admin' => false]);

        $video = Video::create([
            'user_id' => $owner->id,
            'title' => 'Public Video For Moderation',
            'slug' => 'public-video-for-moderation',
            'description' => 'Sample',
            'video_path' => 'https://cdn.hackclub.com/sample/moderation.mp4',
            'video_url' => 'https://cdn.hackclub.com/sample/moderation.mp4',
            'is_public' => true,
        ]);

        $response = $this->actingAs($commenter, 'sanctum')
            ->postJson('/api/v1/videos/'.$video->id.'/comments', [
                'body' => 'Flagged comment',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('field', 'body');

        $this->assertDatabaseMissing('comments', [
            'video_id' => $video->id,
            'user_id' => $commenter->id,
            'body' => 'Flagged comment',
        ]);
    }

    public function test_guest_cannot_comment_on_video(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $video = Video::create([
            'user_id' => $owner->id,
            'title' => 'Public Video',
            'slug' => 'public-video',
            'description' => 'Sample',
            'video_path' => 'https://cdn.hackclub.com/sample/public.mp4',
            'video_url' => 'https://cdn.hackclub.com/sample/public.mp4',
            'is_public' => true,
        ]);

        $this->postJson('/api/v1/videos/'.$video->id.'/comments', [
            'body' => 'I should not be able to post this.',
        ])->assertStatus(401);
    }

    public function test_show_video_increases_views_by_one(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $video = Video::create([
            'user_id' => $owner->id,
            'title' => 'Views Video',
            'slug' => 'views-video',
            'description' => 'Sample',
            'video_path' => 'https://cdn.hackclub.com/sample/views.mp4',
            'video_url' => 'https://cdn.hackclub.com/sample/views.mp4',
            'is_public' => true,
            'views' => 0,
        ]);

        $this->getJson('/api/v1/videos/'.$video->id)->assertStatus(200);

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'views' => 1,
        ]);
    }

    public function test_admin_can_make_video_private(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $video = Video::create([
            'user_id' => $admin->id,
            'title' => 'Public To Private',
            'slug' => 'public-to-private',
            'description' => 'Sample',
            'video_path' => 'https://cdn.hackclub.com/sample/private.mp4',
            'video_url' => 'https://cdn.hackclub.com/sample/private.mp4',
            'is_public' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/videos/'.$video->id.'/private');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('video.id', $video->id)
            ->assertJsonPath('video.is_public', false);

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'is_public' => false,
        ]);

        $this->getJson('/api/v1/videos/'.$video->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This video is private.');
    }
}
