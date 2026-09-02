<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class Podcast extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'playlist_id',
        'user_id',
        'title',
        'slug',
        'description',
        'audio_path',
        'audio_url',
        'mime_type',
        'size',
        'duration',
        'is_public',
        'views',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'size' => 'integer',
        'duration' => 'integer',
        'views' => 'integer',
    ];

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toSearchableArray()
    {
        return [
            'title' => $this->title,
            'description' => $this->description,

        ];
    }
}
