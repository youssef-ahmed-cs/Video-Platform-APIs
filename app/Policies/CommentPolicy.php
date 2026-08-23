<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Video;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommentPolicy
{
    use HandlesAuthorization;

    public function create(User $user, Video $video): bool
    {
        if ($video->is_public) {
            return true;
        }

        return $video->user_id === $user->id || $user->is_admin === true;
    }
}

