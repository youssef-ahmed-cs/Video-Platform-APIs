<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

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
            'videos' => $videos,
        ]);
    }

    public function myVideos()
    {
        $videos = Video::with(['categories:id,name'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json([
            'videos' => $videos,
        ]);
    }

    public function show(Video $video)
    {
        if (! $video->is_public && auth()->id() !== $video->user_id) {
            return response()->json(['message' => 'This video is private.'], 403);
        }

        $video->load(['user:id,name,username', 'categories:id,name']);

        return response()->json([
            'video' => $video,
        ]);
    }

    public function store(Request $request)
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

        $disk = $this->storageDisk();
        $videoFile = $request->file('video');
        $videoPath = $this->storeFile($videoFile, 'videos', $disk);

        $thumbnailPath = null;
        $thumbnailUrl = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $this->storeFile($request->file('thumbnail'), 'video-thumbnails', $disk);
            $thumbnailUrl = Storage::disk($disk)->url($thumbnailPath);
        }

        $slug = $this->generateUniqueSlug($validated['title']);

        $video = Video::create([
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'video_path' => $videoPath,
            'video_url' => Storage::disk($disk)->url($videoPath),
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
            'video' => $video,
        ], 201);
    }

    public function update(Request $request, Video $video)
    {
        if ($video->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

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
            'video' => $video,
        ]);
    }

    public function destroy(Video $video)
    {
        if ($video->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $disk = $this->storageDisk();

        if ($video->video_path) {
            Storage::disk($disk)->delete($video->video_path);
        }

        if ($video->thumbnail_path) {
            Storage::disk($disk)->delete($video->thumbnail_path);
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

        $disk = $this->storageDisk();

        if (! Storage::disk($disk)->exists($video->video_path)) {
            return response()->json(['message' => 'Video file not found.'], 404);
        }

        $video->increment('views');

        if ($disk === 'azure') {
            $stream = Storage::disk($disk)->readStream($video->video_path);
            if (! is_resource($stream)) {
                throw new RuntimeException('Unable to stream Azure video.');
            }

            return response()->stream(function () use ($stream) {
                fpassthru($stream);
                fclose($stream);
            }, 200, [
                'Content-Type' => $video->mime_type ?: 'video/mp4',
                'Content-Length' => Storage::disk($disk)->size($video->video_path),
                'Accept-Ranges' => 'bytes',
            ]);
        }

        return response()->file(
            Storage::disk($disk)->path($video->video_path),
            ['Content-Type' => $video->mime_type ?: 'video/mp4']
        );
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

    protected function storageDisk(): string
    {
        $configuredDisk = config('filesystems.video_disk');

        if (is_string($configuredDisk) && in_array($configuredDisk, ['azure', 'public', 'local'], true)) {
            return $configuredDisk;
        }

        $azureConfigured = ! empty(config('filesystems.disks.azure.connection_string'))
            || (! empty(config('filesystems.disks.azure.name')) && ! empty(config('filesystems.disks.azure.key')));

        return $azureConfigured ? 'azure' : 'public';
    }

    protected function storeFile($file, string $folder, string $disk): string
    {
        $fileName = $this->createStorageFilename($file, $folder);

        Storage::disk($disk)->putFileAs($folder, $file, $fileName, [
            'Content-Type' => $file->getClientMimeType(),
        ]);

        return $folder.'/'.$fileName;
    }

    protected function createStorageFilename($file, string $folder): string
    {
        $extension = $file->getClientOriginalExtension();

        return uniqid('video_', true).'.'.($extension ?: 'bin');
    }
}
