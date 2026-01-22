<?php

namespace App\Providers;

use App\Listeners\TrackJobStatus;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Event;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobExceptionOccurred;
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

        // Register job tracking event listeners
        Event::listen(JobProcessing::class, [TrackJobStatus::class, 'handleJobProcessing']);
        Event::listen(JobProcessed::class, [TrackJobStatus::class, 'handleJobProcessed']);
        Event::listen(JobFailed::class, [TrackJobStatus::class, 'handleJobFailed']);
        Event::listen(JobExceptionOccurred::class, [TrackJobStatus::class, 'handleJobExceptionOccurred']);
    }
}
