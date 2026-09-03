<?php

namespace App\Policies;

use App\Models\Podcast;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PodcastPolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Podcast $podcast): bool
    {
        return $podcast->is_public || ($user && ($podcast->user_id === $user->id || $user->is_admin));
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Podcast $podcast): bool
    {
        return $podcast->user_id === $user->id || $user->is_admin;
    }

    public function delete(User $user, Podcast $podcast): bool
    {
        return $podcast->user_id === $user->id || $user->is_admin;
    }

    public function upload(User $user, Podcast $podcast): bool
    {
        return $podcast->user_id === $user->id || $user->is_admin;
    }
}
