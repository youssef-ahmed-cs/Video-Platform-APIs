<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PodcastRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'playlist_id' => ['nullable', 'integer', 'exists:playlists,id'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }
}
