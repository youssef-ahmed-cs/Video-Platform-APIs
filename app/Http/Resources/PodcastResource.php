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
            'audio_url' => $this->audio_url,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'duration' => $this->duration,
            'is_public' => (bool) $this->is_public,
            'views' => (int) $this->views,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
