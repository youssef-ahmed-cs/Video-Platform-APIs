<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PodcastRequest;
use App\Http\Resources\PodcastResource;
use App\Models\Podcast;
use App\Models\Playlist;
use Illuminate\Http\Request;

class PodcastController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);

        $paginator = Podcast::with('playlist')
            ->where(function ($q) {
                $q->where('is_public', true)
                    ->orWhere('user_id', auth()->id());
            })
            ->latest()
            ->paginate($perPage);

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

    public function show(Podcast $podcast)
    {
        if (! $podcast->is_public && auth()->id() !== $podcast->user_id && (!auth()->check() || !auth()->user()->is_admin)) {
            return response()->json(['success' => false, 'message' => 'This podcast is private.'], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Podcast retrieved successfully.',
            'data' => [
                'podcast' => new PodcastResource($podcast),
            ],
        ]);
    }

    public function store(PodcastRequest $request)
    {
        $validated = $request->validated();

        $playlist = null;
        if (!empty($validated['playlist_id'])) {
            $playlist = Playlist::findOrFail($validated['playlist_id']);
            // if playlist is system, only admin can attach
            if ($playlist->is_system && !auth()->user()->is_admin) {
                return response()->json(['success' => false, 'message' => 'Cannot add podcast to a system playlist.'], 403);
            }
            // if user does not own playlist and not admin
            if ($playlist->user_id !== auth()->id() && !auth()->user()->is_admin) {
                return response()->json(['success' => false, 'message' => 'Unauthorized to add to this playlist.'], 403);
            }
        }

        $podcast = Podcast::create([
            'playlist_id' => $playlist?->id,
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'slug' => \Illuminate\Support\Str::slug($validated['title']) ?: 'podcast-'.time(),
            'description' => $validated['description'] ?? null,
            'is_public' => $validated['is_public'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Podcast created successfully.',
            'data' => ['podcast' => new PodcastResource($podcast)],
        ], 201);
    }

    public function update(Request $request, Podcast $podcast)
    {
        if ($podcast->user_id !== auth()->id() && !auth()->user()->is_admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        if (!empty($validated['title'])) {
            $validated['slug'] = \Illuminate\Support\Str::slug($validated['title']) ?: $podcast->slug;
        }

        $podcast->fill($validated);
        $podcast->save();

        return response()->json([
            'success' => true,
            'message' => 'Podcast updated successfully.',
            'data' => ['podcast' => new PodcastResource($podcast)],
        ]);
    }

    public function destroy(Podcast $podcast)
    {
        if ($podcast->user_id !== auth()->id() && !auth()->user()->is_admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $podcast->delete();

        return response()->json(['success' => true, 'message' => 'Podcast removed successfully.']);
    }

    public function upload(Request $request, Podcast $podcast, \App\Services\AzureBlobStorageService $azure)
    {
        if ($podcast->user_id !== auth()->id() && !auth()->user()->is_admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $file = $request->file('file');
        if (! $file) {
            return response()->json(['success' => false, 'message' => 'No file provided.'], 400);
        }

        $path = $azure->uploadAudio($file, 'podcasts');
        if (! $path) {
            return response()->json(['success' => false, 'message' => 'Failed to upload audio.'], 500);
        }

        $podcast->audio_path = $path;
        $podcast->audio_url = $path; // service returns full URL
        $podcast->mime_type = $file->getClientMimeType();
        $podcast->size = $file->getSize();
        $podcast->save();

        return response()->json([
            'success' => true,
            'message' => 'Audio uploaded successfully.',
            'data' => ['podcast' => new PodcastResource($podcast)],
        ]);
    }

    public function search(Request $request)
    {
        $q = $request->input('query');
        if (! $q) {
            return response()->json(['success' => false, 'message' => 'Query parameter is required.'], 400);
        }

        $podcasts = Podcast::where('title', 'like', '%'.str_replace('%', '\\%', $q).'%')
            ->where(function ($query) {
                $query->where('is_public', true)
                    ->orWhere('user_id', auth()->id());
            })->get();

        return response()->json([
            'success' => true,
            'message' => 'Search results returned.',
            'data' => ['podcasts' => PodcastResource::collection($podcasts)],
        ]);
    }
}
