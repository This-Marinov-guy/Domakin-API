<?php

namespace App\Providers;

use App\Http\Middleware\AxiomLoggerMiddleware;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;

class AxiomServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register axiom configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/axiom.php',
            'services.axiom'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/axiom.php' => config_path('axiom.php'),
        ], 'axiom-config');

        // Register middleware for API routes
        if (ENV('AXIOM_LOGGING_ENABLED')) {
            $router = $this->app['router'];
            $router->aliasMiddleware('axiom.logger', AxiomLoggerMiddleware::class);

            // Apply to API routes automatically when you want to apply to all API routes
            // $router->prependMiddlewareToGroup('api', AxiomLoggerMiddleware::class);
        }
    }
}
