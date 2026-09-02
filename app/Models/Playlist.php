<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Playlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'is_public',
        'is_system',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class)->withTimestamps();
    }

    public function podcasts(): HasMany
    {
        return $this->hasMany(Podcast::class);
    }
}

