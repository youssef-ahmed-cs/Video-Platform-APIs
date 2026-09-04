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
        return $podcast->is_public || ($user && $user->is_admin === true);
    }

    public function create(User $user): bool
    {
        return $user->is_admin === true;
    }

    public function update(User $user, Podcast $podcast): bool
    {
        return $user->is_admin === true;
    }

    public function delete(User $user, Podcast $podcast): bool
    {
        return $user->is_admin === true;
    }

    public function upload(User $user, Podcast $podcast): bool
    {
        return $user->is_admin === true;
    }
}
