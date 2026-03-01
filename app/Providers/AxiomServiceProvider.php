<?php

namespace App\Providers;

use App\Http\Middleware\AxiomLoggerMiddleware;
use App\Http\Middleware\AxiomWebhookLoggerMiddleware;
use Illuminate\Support\ServiceProvider;

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

        $router = $this->app['router'];
        $router->aliasMiddleware('axiom.logger', AxiomLoggerMiddleware::class);
        $router->aliasMiddleware('axiom.webhook.logger', AxiomWebhookLoggerMiddleware::class);
    }
}
