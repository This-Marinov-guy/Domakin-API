<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

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
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // Define API rate limiter: default 60 requests/min per IP; stricter for auth endpoints
        RateLimiter::for('api', function (Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(config('rate_limits.api_per_minute'))
                ->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(config('rate_limits.login_per_minute'))
                ->by($email.$request->ip());
        });
    }
}
