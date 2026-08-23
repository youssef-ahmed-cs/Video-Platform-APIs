<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Video extends Model
{
    use HasFactory , Searchable;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'video_path',
        'video_url',
        'thumbnail_path',
        'thumbnail_url',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class)->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function toSearchableArray()
    {
        return [
            'title' => $this->title,
        ];
    }
}
