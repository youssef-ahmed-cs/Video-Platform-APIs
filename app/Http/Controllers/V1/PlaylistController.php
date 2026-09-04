<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlayListRequest;
use App\Http\Resources\PlaylistResource;
use App\Models\Playlist;
use App\Models\Video;
use App\Models\Podcast;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlaylistController extends Controller
{
    public function index()
    {
        $playlists = Playlist::with(['videos:id,title', 'user:id,name,username'])
            ->where(function ($query) {
                $query->where('is_public', true)
                    ->orWhere('user_id', auth()->id());
            })
            ->latest()
            ->get();

        return response()->json([
            'playlists' => PlaylistResource::collection($playlists),
        ]);
    }

    public function show(Playlist $playlist)
    {
        if (! $playlist->is_public && auth()->id() !== $playlist->user_id) {
            return response()->json(['message' => 'This playlist is private.'], 403);
        }

        $playlist->load([
            'videos' => fn ($query) => $query->where('is_public', true)->orWhere('user_id', auth()->id()),
            'podcasts' => fn ($query) => auth()->check() && auth()->user()->is_admin
                ? $query
                : $query->where('is_public', true),
            'user:id,name,username',
        ]);

        return response()->json([
            'playlist' => new PlaylistResource($playlist),
        ]);
    }

    public function store(PlayListRequest $request)
    {
        $validated = $request->validated();

        $isSystem = auth()->user()->is_admin === true;

        $playlist = Playlist::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_public' => $validated['is_public'] ?? true,
            'is_system' => $isSystem,
        ]);

        // If admin created the playlist, create a default system podcast for it
        if ($isSystem) {
            $title = 'System podcast for ' . $playlist->name;
            $slug = Str::slug($title) ?: 'podcast-' . time();
            if (Podcast::where('slug', $slug)->exists()) {
                $slug = $slug . '-' . time();
            }

            Podcast::create([
                'playlist_id' => $playlist->id,
                'user_id' => auth()->id(),
                'title' => $title,
                'slug' => $slug,
                'description' => 'Automatically created system podcast for playlist: ' . $playlist->name,
                'is_public' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Playlist created successfully.',
            'data' => [
                'playlist' => new PlaylistResource($playlist),
            ],
        ], 201);
    }

    public function update(Request $request, Playlist $playlist)
    {
        if ($playlist->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name'], $playlist->id);
        }

        $playlist->fill($validated);
        $playlist->save();

        return response()->json([
            'message' => 'Playlist updated successfully.',
            'playlist' => new PlaylistResource($playlist),
        ]);
    }

    public function destroy(Playlist $playlist)
    {
        if ($playlist->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $playlist->delete();

        return response()->json([
            'message' => 'Playlist removed successfully.',
        ]);
    }

    public function addVideo(Request $request, Playlist $playlist)
    {
        if ($playlist->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'video_id' => ['required', 'integer', 'exists:videos,id'],
        ]);

        $video = Video::findOrFail($validated['video_id']);
        if ($video->user_id !== auth()->id()) {
            return response()->json(['message' => 'Only your own videos can be added to a playlist.'], 403);
        }

        $playlist->videos()->syncWithoutDetaching([$video->id]);

        return response()->json([
            'message' => 'Video added to playlist.',
            'playlist' => new PlaylistResource($playlist->fresh(['videos'])),
        ]);
    }

    public function removeVideo(Playlist $playlist, Video $video)
    {
        if ($playlist->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $playlist->videos()->detach($video->id);

        return response()->json([
            'message' => 'Video removed from playlist.',
            'playlist' => new PlaylistResource($playlist->fresh(['videos'])),
        ]);
    }

    public function addPodcast(Request $request, Playlist $playlist)
    {
        if (!auth()->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $validated = $request->validate([
            'podcast_id' => ['required', 'integer', 'exists:podcasts,id'],
        ]);

        $podcast = Podcast::findOrFail($validated['podcast_id']);

        $podcast->playlist_id = $playlist->id;
        $podcast->save();

        return response()->json([
            'message' => 'Podcast added to playlist.',
            'playlist' => new PlaylistResource($playlist->fresh(['videos', 'podcasts'])),
        ]);
    }

    public function removePodcast(Playlist $playlist, Podcast $podcast)
    {
        if (!auth()->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        if ($podcast->playlist_id === $playlist->id) {
            $podcast->playlist_id = null;
            $podcast->save();
        }

        return response()->json([
            'message' => 'Podcast removed from playlist.',
            'playlist' => new PlaylistResource($playlist->fresh(['videos', 'podcasts'])),
        ]);
    }

    protected function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'playlist';
        $slug = $base;
        $counter = 1;

        while (Playlist::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }
        return $slug;
    }
}
