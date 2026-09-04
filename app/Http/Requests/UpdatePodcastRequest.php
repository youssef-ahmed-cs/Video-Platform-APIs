<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePodcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'playlist_id' => ['nullable', 'integer', 'exists:playlists,id'],
            'is_public' => ['sometimes', 'boolean'],
            'audio' => ['nullable', 'file', 'mimes:mp3,wav,ogg,aac,m4a,flac,opus,mp4', 'max:512000'],
            'audio_url' => ['nullable', 'url', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'cover_image_url' => ['nullable', 'url', 'max:2048'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'episode_number' => ['nullable', 'integer', 'min:1'],
            'season_number' => ['nullable', 'integer', 'min:1'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
