<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_admin === true;
    }

    public function update(User $user, Category $category): bool
    {
        return $user->is_admin === true;
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->is_admin === true;
    }
}
