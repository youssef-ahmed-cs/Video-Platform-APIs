<?php

namespace App\Observers;

use App\Mail\WelcomeEmailMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthObserver
{
    public function created(User $user): void
    {
        Log::info('New user registered: ' . $user->email);
        Mail::to($user->email)->send(new WelcomeEmailMail($user));
        Log::emergency();
    }
}
