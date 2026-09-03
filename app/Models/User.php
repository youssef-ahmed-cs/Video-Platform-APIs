<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Observers\AuthObserver;
use App\Observers\ProfileObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;

#[ObservedBy([AuthObserver::class])]
#[ObservedBy([ProfileObserver::class])]
#[Fillable(['name', 'email', 'password', 'username', 'bio', 'avatar_url', 'is_admin', 'account_number'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (! empty($user->account_number)) {
                return;
            }

            $user->account_number = self::generateUniqueAccountNumber();
        });
    }

    protected static function generateUniqueAccountNumber(): string
    {
        do {
            $accountNumber = 'ACC-'.strtoupper(Str::random(10));
        } while (self::withTrashed()->where('account_number', $accountNumber)->exists());

        return $accountNumber;
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function playlists()
    {
        return $this->hasMany(Playlist::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function podcasts()
    {
        return $this->hasMany(Podcast::class);
    }
}
