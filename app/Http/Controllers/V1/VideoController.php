<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateVideoRequest;
use App\Http\Requests\UpdateVideoRequest;
use App\Http\Resources\VideoResource;
use App\Models\User;
use App\Models\Video;
use App\Notifications\NewVideoNotification;
use App\Services\ModerationService;
use App\Services\VideoCDNStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    public function index()
    {
        $videos = Video::with(['user:id,name,username', 'categories:id,name'])
            ->when(auth()->check(), function ($query) {
                $query->where(function ($q) {
                    $q->where('is_public', true)
                        ->orWhere('user_id', auth()->id());
                });
            }, function ($query) {
                $query->where('is_public', true);
            })
            ->latest()
            ->get();

        return $this->successResponse([
            'videos' => VideoResource::collection($videos),
        ], 'Videos fetched successfully.');
    }

    public function myVideos()
    {
        $videos = Video::with(['categories:id,name'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return $this->successResponse([
            'videos' => VideoResource::collection($videos),
        ], 'Your videos fetched successfully.');
    }

    public function show(Video $video)
    {
        if (!$video->is_public && auth()->id() !== $video->user_id) {
            return $this->errorResponse('This video is private.', 403);
        }

        $video->increment('views');
        $video->load(['user:id,name,username', 'categories:id,name']);

        return $this->successResponse([
            'video' => new VideoResource($video),
        ], 'Video fetched successfully.');
    }

    public function store(
        CreateVideoRequest $request,
        VideoCDNStorage $videoCdnStorage,
        ModerationService $moderationService
    )
    {
        $this->authorize('create', Video::class);

        $validated = $request->validated();
        $moderationChecks = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
        ];

        foreach ($moderationChecks as $field => $value) {
            if (trim($value) === '') {
                continue;
            }

            $moderation = $moderationService->moderateContent($value);

            if (isset($moderation['error'])) {
                return $this->errorResponse('Content moderation is currently unavailable.', 503, [
                    'field' => $field,
                    'error' => $moderation['error'],
                ]);
            }

            if ($moderation['flagged'] ?? false) {
                return $this->errorResponse("The {$field} contains content that violates moderation policies.", 422, [
                    'field' => $field,
                    'moderation' => $moderation,
                ]);
            }
        }

        $videoFile = $request->file('video');

        if ($videoFile) {
            $videoUrl = $videoCdnStorage->uploadVideo($videoFile, 'videos', auth()->user()?->name);
            $mimeType = $videoFile->getMimeType();
            $size = $videoFile->getSize();
        } else {
            $videoUrl = $videoCdnStorage->uploadVideoFromUrl($validated['video_url'], auth()->user()?->name);
            $mimeType = null;
            $size = 0;
        }

        $thumbnailPath = null;
        $thumbnailUrl = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailUrl = $videoCdnStorage->uploadImage($request->file('thumbnail'), 'video-thumbnails', auth()->user()?->name);
            $thumbnailPath = basename(parse_url($thumbnailUrl, PHP_URL_PATH) ?: $thumbnailUrl);
        }

        $slug = $this->generateUniqueSlug($validated['title']);

        $video = Video::create([
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'video_path' => $videoUrl,
            'video_url' => $videoUrl,
            'thumbnail_path' => $thumbnailPath,
            'thumbnail_url' => $thumbnailUrl,
            'mime_type' => $mimeType,
            'size' => $size,
            'duration' => $request->input('duration'),
            'is_public' => $validated['is_public'] ?? true,
        ]);

        if (!empty($validated['category_ids'] ?? [])) {
            $video->categories()->sync($validated['category_ids']);
        }

        User::query()->chunkById(100, function ($users) use ($video) {
            Notification::send(
                $users,
                new NewVideoNotification($video)
            );
        });
        $video->load(['categories:id,name']);

        return $this->successResponse([
            'video' => new VideoResource($video),
        ], 'Video uploaded successfully.', 201);
    }

    public function update(UpdateVideoRequest $request, Video $video)
    {
        $this->authorize('update', $video);

        $validated = $request->validated();

        if (isset($validated['title'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $video->id);
        }

        $video->fill($validated);
        $video->save();

        if (array_key_exists('category_ids', $validated)) {
            $video->categories()->sync($validated['category_ids']);
        }

        $video->load(['categories:id,name']);

        return $this->successResponse([
            'video' => new VideoResource($video),
        ], 'Video updated successfully.');
    }

    public function destroy(Video $video, VideoCDNStorage $videoCdnStorage)
    {
        $this->authorize('delete', $video);

        if ($video->video_url) {
            $videoCdnStorage->deleteUpload($video->video_url);
        }

        if ($video->thumbnail_url) {
            $videoCdnStorage->deleteUpload($video->thumbnail_url);
        }

        $video->delete();

        return $this->successResponse([], 'Video removed successfully.');
    }

    public function watch(Video $video)
    {
        if (!$video->is_public && auth()->id() !== $video->user_id) {
            return $this->errorResponse('This video is private.', 403);
        }

        if (empty($video->video_url)) {
            return $this->errorResponse('Video file not found.', 404);
        }

        $video->increment('views');

        return redirect()->away($video->video_url);
    }

    protected function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'video';
        $slug = $base;
        $counter = 1;

        while (Video::where('slug', $slug)
            ->when($ignoreId, fn($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function search(Request $request)
    {
        $query = $request->input('query');

        if (!$query) {
            return $this->errorResponse('Query parameter is required.', 400);
        }

        $videos = Video::search($query)->get();

        return $this->successResponse([
            'videos' => VideoResource::collection($videos),
        ], 'Videos fetched successfully.');
    }

    public function notification()
    {
        $user = auth()->user();
        $notifications = $user->notifications()->latest()->get();

        return $this->successResponse([
            'notifications' => $notifications,
        ], 'Notifications fetched successfully.');
    }

    public function makePrivate(Video $video)
    {
        $this->authorize('update', $video);

        if (!$video->is_public) {
            return $this->successResponse([
                'video' => new VideoResource($video),
            ], 'Video is already private.');
        }

        $video->is_public = false;
        $video->save();
        $video->load(['categories:id,name']);

        return $this->successResponse([
            'video' => new VideoResource($video),
        ], 'Video is now private.');
    }

}
