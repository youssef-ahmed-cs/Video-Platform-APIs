<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PodcastRequest;
use App\Http\Requests\UpdatePodcastRequest;
use App\Http\Resources\PodcastResource;
use App\Models\Playlist;
use App\Models\Podcast;
use App\Models\User;
use App\Notifications\NewPodcastNotification;
use App\Services\AzureBlobStorageService;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class PodcastController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = Podcast::with(['playlist:id,name,slug', 'user:id,name,username,avatar_url', 'categories:id,name,slug'])
            ->where(function ($q) {
                if (auth()->check() && auth()->user()->is_admin) {
                    return;
                }

                $q->where('is_public', true);
                if (auth()->check()) {
                    $q->orWhere('user_id', auth()->id());
                }
            })
            ->when($request->filled('category_id'), function ($q) use ($request) {
                $q->whereHas('categories', function ($cq) use ($request) {
                    $cq->where('categories.id', $request->input('category_id'));
                });
            })
            ->when($request->filled('playlist_id'), function ($q) use ($request) {
                $q->where('playlist_id', $request->input('playlist_id'));
            })
            ->when($request->filled('user_id'), function ($q) use ($request) {
                $q->where('user_id', $request->input('user_id'));
            })
            ->when($request->filled('query'), function ($q) use ($request) {
                $safe = str_replace('%', '\\%', $request->input('query'));
                $q->where(function ($sq) use ($safe) {
                    $sq->where('title', 'like', "%{$safe}%")
                        ->orWhere('description', 'like', "%{$safe}%");
                });
            });

        $sort = $request->input('sort');
        if ($sort === 'views') {
            $query->orderByDesc('views');
        } elseif ($sort === 'oldest') {
            $query->oldest();
        } else {
            $query->latest();
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Podcasts retrieved successfully.',
            'data' => [
                'podcasts' => PodcastResource::collection($paginator->items()),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function myPodcasts(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $paginator = Podcast::with(['playlist:id,name,slug', 'categories:id,name,slug'])
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Your podcasts retrieved successfully.',
            'data' => [
                'podcasts' => PodcastResource::collection($paginator->items()),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function show(Podcast $podcast): JsonResponse
    {
        if (! $podcast->is_public && auth()->id() !== $podcast->user_id && (!auth()->check() || !auth()->user()->is_admin)) {
            return response()->json(['success' => false, 'message' => 'This podcast is private.'], 403);
        }

        $podcast->increment('views');
        $podcast->load(['playlist:id,name,slug', 'user:id,name,username,avatar_url', 'categories:id,name,slug']);

        return response()->json([
            'success' => true,
            'message' => 'Podcast retrieved successfully.',
            'data' => [
                'podcast' => new PodcastResource($podcast),
            ],
        ]);
    }

    public function listen(Podcast $podcast)
    {
        if (! $podcast->is_public && auth()->id() !== $podcast->user_id && (!auth()->check() || !auth()->user()->is_admin)) {
            return response()->json(['success' => false, 'message' => 'This podcast is private.'], 403);
        }

        if (empty($podcast->audio_url)) {
            return response()->json(['success' => false, 'message' => 'Audio file not found.'], 404);
        }

        $podcast->increment('views');

        return redirect()->away($podcast->audio_url);
    }

    public function store(
        PodcastRequest $request,
        AzureBlobStorageService $azure,
        ModerationService $moderationService
    ): JsonResponse {
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
                return response()->json([
                    'success' => false,
                    'message' => 'Content moderation is currently unavailable.',
                    'field' => $field,
                    'error' => $moderation['error'],
                ], 503);
            }

            if ($moderation['flagged'] ?? false) {
                return response()->json([
                    'success' => false,
                    'message' => "The {$field} contains content that violates moderation policies.",
                    'field' => $field,
                    'moderation' => $moderation,
                ], 422);
            }
        }

        $playlist = null;
        if (!empty($validated['playlist_id'])) {
            $playlist = Playlist::findOrFail($validated['playlist_id']);

            if ($playlist->is_system && !auth()->user()->is_admin) {
                return response()->json(['success' => false, 'message' => 'Cannot add podcast to a system playlist.'], 403);
            }

            if ($playlist->user_id !== auth()->id() && !auth()->user()->is_admin) {
                return response()->json(['success' => false, 'message' => 'Unauthorized to add to this playlist.'], 403);
            }
        }

        $audioPath = null;
        $audioUrl = $validated['audio_url'] ?? null;
        $mimeType = null;
        $size = null;

        if ($request->hasFile('audio')) {
            $audioFile = $request->file('audio');
            $uploadedAudio = $azure->uploadAudio($audioFile, 'podcasts');
            if ($uploadedAudio) {
                $audioPath = $uploadedAudio;
                $audioUrl = $uploadedAudio;
                $mimeType = $audioFile->getClientMimeType();
                $size = $audioFile->getSize();
            }
        }

        $coverImagePath = null;
        $coverImageUrl = $validated['cover_image_url'] ?? null;

        if ($request->hasFile('cover_image')) {
            $coverFile = $request->file('cover_image');
            $uploadedCover = $azure->uploadImage($coverFile, 'podcast-covers');
            if ($uploadedCover) {
                $coverImagePath = $uploadedCover;
                $coverImageUrl = $uploadedCover;
            }
        }

        $slug = $this->generateUniqueSlug($validated['title']);

        $podcast = Podcast::create([
            'playlist_id' => $playlist?->id,
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'cover_image_path' => $coverImagePath,
            'cover_image_url' => $coverImageUrl,
            'audio_path' => $audioPath,
            'audio_url' => $audioUrl,
            'mime_type' => $mimeType,
            'size' => $size,
            'duration' => $validated['duration'] ?? null,
            'episode_number' => $validated['episode_number'] ?? null,
            'season_number' => $validated['season_number'] ?? null,
            'is_public' => $validated['is_public'] ?? true,
        ]);

        if (!empty($validated['category_ids'])) {
            $podcast->categories()->sync($validated['category_ids']);
        }

        if ($podcast->is_public) {
            User::query()->chunkById(100, function ($users) use ($podcast) {
                Notification::send($users, new NewPodcastNotification($podcast));
            });
        }

        $podcast->load(['playlist:id,name,slug', 'user:id,name,username,avatar_url', 'categories:id,name,slug']);

        return response()->json([
            'success' => true,
            'message' => 'Podcast created successfully.',
            'data' => ['podcast' => new PodcastResource($podcast)],
        ], 201);
    }

    public function update(
        UpdatePodcastRequest $request,
        Podcast $podcast,
        AzureBlobStorageService $azure,
        ModerationService $moderationService
    ): JsonResponse {
        if ($podcast->user_id !== auth()->id() && !auth()->user()->is_admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validated();

        foreach (['title', 'description'] as $field) {
            if (isset($validated[$field]) && trim($validated[$field]) !== '') {
                $moderation = $moderationService->moderateContent($validated[$field]);

                if (isset($moderation['error'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Content moderation is currently unavailable.',
                        'field' => $field,
                        'error' => $moderation['error'],
                    ], 503);
                }

                if ($moderation['flagged'] ?? false) {
                    return response()->json([
                        'success' => false,
                        'message' => "The {$field} contains content that violates moderation policies.",
                        'field' => $field,
                        'moderation' => $moderation,
                    ], 422);
                }
            }
        }

        if (!empty($validated['title'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $podcast->id);
        }

        if (array_key_exists('playlist_id', $validated)) {
            if (!empty($validated['playlist_id'])) {
                $playlist = Playlist::findOrFail($validated['playlist_id']);

                if ($playlist->is_system && !auth()->user()->is_admin) {
                    return response()->json(['success' => false, 'message' => 'Cannot add podcast to a system playlist.'], 403);
                }

                if ($playlist->user_id !== auth()->id() && !auth()->user()->is_admin) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized to add to this playlist.'], 403);
                }
            }
        }

        if ($request->hasFile('audio')) {
            if ($podcast->audio_path) {
                $azure->deleteFile($podcast->audio_path, 'podcasts');
            }
            $audioFile = $request->file('audio');
            $uploadedAudio = $azure->uploadAudio($audioFile, 'podcasts');
            if ($uploadedAudio) {
                $validated['audio_path'] = $uploadedAudio;
                $validated['audio_url'] = $uploadedAudio;
                $validated['mime_type'] = $audioFile->getClientMimeType();
                $validated['size'] = $audioFile->getSize();
            }
        }

        if ($request->hasFile('cover_image')) {
            if ($podcast->cover_image_path) {
                $azure->deleteFile($podcast->cover_image_path, 'podcast-covers');
            }
            $coverFile = $request->file('cover_image');
            $uploadedCover = $azure->uploadImage($coverFile, 'podcast-covers');
            if ($uploadedCover) {
                $validated['cover_image_path'] = $uploadedCover;
                $validated['cover_image_url'] = $uploadedCover;
            }
        }

        if (array_key_exists('category_ids', $validated)) {
            $podcast->categories()->sync($validated['category_ids'] ?? []);
        }

        $podcast->fill($validated);
        $podcast->save();

        $podcast->load(['playlist:id,name,slug', 'user:id,name,username,avatar_url', 'categories:id,name,slug']);

        return response()->json([
            'success' => true,
            'message' => 'Podcast updated successfully.',
            'data' => ['podcast' => new PodcastResource($podcast)],
        ]);
    }

    public function destroy(Podcast $podcast, AzureBlobStorageService $azure): JsonResponse
    {
        if ($podcast->user_id !== auth()->id() && !auth()->user()->is_admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($podcast->audio_path || $podcast->audio_url) {
            $azure->deleteFile($podcast->audio_path ?: $podcast->audio_url, 'podcasts');
        }

        if ($podcast->cover_image_path || $podcast->cover_image_url) {
            $azure->deleteFile($podcast->cover_image_path ?: $podcast->cover_image_url, 'podcast-covers');
        }

        $podcast->delete();

        return response()->json(['success' => true, 'message' => 'Podcast removed successfully.']);
    }

    public function upload(Request $request, Podcast $podcast, AzureBlobStorageService $azure): JsonResponse
    {
        if ($podcast->user_id !== auth()->id() && !auth()->user()->is_admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $file = $request->file('file') ?: $request->file('audio') ?: $request->file('cover_image');
        if (! $file) {
            return response()->json(['success' => false, 'message' => 'No file provided.'], 400);
        }

        $mime = $file->getClientMimeType() ?: '';
        $ext = strtolower($file->getClientOriginalExtension());
        $isImage = str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);

        if ($isImage) {
            $request->validate([
                'file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
                'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            ]);

            if ($podcast->cover_image_path) {
                $azure->deleteFile($podcast->cover_image_path, 'podcast-covers');
            }

            $path = $azure->uploadImage($file, 'podcast-covers');
            if (! $path) {
                return response()->json(['success' => false, 'message' => 'Failed to upload cover image.'], 500);
            }

            $podcast->cover_image_path = $path;
            $podcast->cover_image_url = $path;
            $podcast->save();

            return response()->json([
                'success' => true,
                'message' => 'Cover image uploaded successfully.',
                'data' => ['podcast' => new PodcastResource($podcast)],
            ]);
        }

        $allowedAudioExts = ['mp3', 'wav', 'ogg', 'aac', 'm4a', 'flac', 'opus', 'mp4'];
        if (!in_array($ext, $allowedAudioExts) && !str_starts_with($mime, 'audio/')) {
            return response()->json([
                'success' => false,
                'message' => 'The file must be a valid audio file (mp3, wav, ogg, aac, m4a, flac, opus).',
            ], 422);
        }

        if ($podcast->audio_path) {
            $azure->deleteFile($podcast->audio_path, 'podcasts');
        }

        $path = $azure->uploadAudio($file, 'podcasts');
        if (! $path) {
            return response()->json(['success' => false, 'message' => 'Failed to upload audio.'], 500);
        }

        $podcast->audio_path = $path;
        $podcast->audio_url = $path;
        $podcast->mime_type = $file->getClientMimeType();
        $podcast->size = $file->getSize();
        $podcast->save();

        return response()->json([
            'success' => true,
            'message' => 'Audio uploaded successfully.',
            'data' => ['podcast' => new PodcastResource($podcast)],
        ]);
    }

    public function makePrivate(Podcast $podcast): JsonResponse
    {
        if ($podcast->user_id !== auth()->id() && !auth()->user()->is_admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if (! $podcast->is_public) {
            return response()->json([
                'success' => true,
                'message' => 'Podcast is already private.',
                'data' => ['podcast' => new PodcastResource($podcast)],
            ]);
        }

        $podcast->is_public = false;
        $podcast->save();

        return response()->json([
            'success' => true,
            'message' => 'Podcast is now private.',
            'data' => ['podcast' => new PodcastResource($podcast)],
        ]);
    }

    public function makePublic(Podcast $podcast): JsonResponse
    {
        if ($podcast->user_id !== auth()->id() && !auth()->user()->is_admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($podcast->is_public) {
            return response()->json([
                'success' => true,
                'message' => 'Podcast is already public.',
                'data' => ['podcast' => new PodcastResource($podcast)],
            ]);
        }

        $podcast->is_public = true;
        $podcast->save();

        return response()->json([
            'success' => true,
            'message' => 'Podcast is now public.',
            'data' => ['podcast' => new PodcastResource($podcast)],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = $request->input('query');
        if (! $q) {
            return response()->json(['success' => false, 'message' => 'Query parameter is required.'], 400);
        }

        $safeQ = str_replace('%', '\\%', $q);

        $podcasts = Podcast::with(['playlist:id,name,slug', 'user:id,name,username,avatar_url', 'categories:id,name,slug'])
            ->where(function ($query) use ($safeQ) {
                $query->where('title', 'like', "%{$safeQ}%")
                    ->orWhere('description', 'like', "%{$safeQ}%");
            })
            ->where(function ($query) {
                if (auth()->check() && auth()->user()->is_admin) {
                    return;
                }

                $query->where('is_public', true);
                if (auth()->check()) {
                    $query->orWhere('user_id', auth()->id());
                }
            })
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Search results returned.',
            'data' => ['podcasts' => PodcastResource::collection($podcasts)],
        ]);
    }

    protected function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'podcast';
        $slug = $base;
        $counter = 1;

        while (Podcast::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
