<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use App\Services\VideoCDNStorage;
use Illuminate\Http\Request;
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

        return response()->json([
            'videos' => VideoResource::collection($videos),
        ]);
    }

    public function myVideos()
    {
        $videos = Video::with(['categories:id,name'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json([
            'videos' => VideoResource::collection($videos),
        ]);
    }

    public function show(Video $video)
    {
        if (! $video->is_public && auth()->id() !== $video->user_id) {
            return response()->json(['message' => 'This video is private.'], 403);
        }

        $video->load(['user:id,name,username', 'categories:id,name']);

        return response()->json([
            'video' => new VideoResource($video),
        ]);
    }

    public function store(Request $request, VideoCDNStorage $videoCdnStorage)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $videoFile = $request->file('video');
        $videoUrl = $videoCdnStorage->uploadVideo($videoFile, 'videos', auth()->user()?->name);

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
            'mime_type' => $videoFile->getMimeType(),
            'size' => $videoFile->getSize(),
            'duration' => $request->input('duration'),
            'is_public' => $validated['is_public'] ?? true,
        ]);

        if (! empty($validated['category_ids'] ?? [])) {
            $video->categories()->sync($validated['category_ids']);
        }

        $video->load(['categories:id,name']);

        return response()->json([
            'message' => 'Video uploaded successfully.',
            'video' => new VideoResource($video),
        ], 201);
    }

    public function update(Request $request, Video $video)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_public' => ['sometimes', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $video->id);
        }

        $video->fill($validated);
        $video->save();

        if (array_key_exists('category_ids', $validated)) {
            $video->categories()->sync($validated['category_ids']);
        }

        $video->load(['categories:id,name']);

        return response()->json([
            'message' => 'Video updated successfully.',
            'video' => new VideoResource($video),
        ]);
    }

    public function destroy(Video $video, VideoCDNStorage $videoCdnStorage)
    {
        if ($video->video_url) {
            $videoCdnStorage->deleteUpload($video->video_url);
        }

        if ($video->thumbnail_url) {
            $videoCdnStorage->deleteUpload($video->thumbnail_url);
        }

        $video->delete();

        return response()->json([
            'message' => 'Video removed successfully.',
        ]);
    }

    public function watch(Video $video)
    {
        if (! $video->is_public && auth()->id() !== $video->user_id) {
            return response()->json(['message' => 'This video is private.'], 403);
        }

        if (empty($video->video_url)) {
            return response()->json(['message' => 'Video file not found.'], 404);
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
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

}
