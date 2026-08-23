<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Video;
use App\Policies\CategoryPolicy;
use App\Policies\VideoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            return Password::min(8)
                ->mixedCase()
                ->uncompromised()
                ->numbers()->letters();
        });

        Gate::policy(Video::class, VideoPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);

        Gate::define('restore-user', function ($user) {
            return $user->is_admin === true;
        });
    }
}
