<?php

namespace App\Models;

use App\Policies\PodcastPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'cover_image_path',
        'cover_image_url',
        'audio_path',
        'audio_url',
        'mime_type',
        'size',
        'duration',
        'episode_number',
        'season_number',
        'is_public',
        'views',
    ];


    protected $casts = [
        'is_public' => 'boolean',
        'size' => 'integer',
        'duration' => 'integer',
        'views' => 'integer',
        'episode_number' => 'integer',
        'season_number' => 'integer',
    ];

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user && $user->is_admin) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('is_public', true);
            if ($user) {
                $q->orWhere('user_id', $user->id);
            }
        });
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
        ];
    }
}
