<?php

namespace App\Observers;

use App\Mail\AccountForceDeleteMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class ProfileObserver
{
    public function creating(User $user): void
    {

    }

    public function created(User $user): void
    {
    }

    public function updating(User $user): void
    {
    }

    public function updated(User $user): void
    {
    }

    public function deleting(User $user): void
    {
    }

    public function deleted(User $user): void
    {
    }

    public function restoring(User $user): void
    {
    }

    public function restored(User $user): void
    {
    }

    public function forceDeleting(User $user): void
    {
        Mail::to($user->email)->send(new AccountForceDeleteMail($user));
    }

    public function forceDeleted(User $user): void
    {
    }
}
