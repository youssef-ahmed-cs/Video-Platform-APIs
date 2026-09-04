<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PodcastResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'playlist_id' => $this->playlist_id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_image_url' => $this->cover_image_url,
            'audio_url' => $this->audio_url,
            'listen_url' => url('/' . $this->slug),
            'stream_url' => url('/' . $this->slug . '?stream=1'),
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'duration' => $this->duration,
            'episode_number' => $this->episode_number,
            'season_number' => $this->season_number,
            'is_public' => (bool) $this->is_public,
            'views' => (int) $this->views,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'username' => $this->user->username,
                    'avatar_url' => $this->user->avatar_url,
                ];
            }),
            'playlist' => new PlaylistResource($this->whenLoaded('playlist')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
