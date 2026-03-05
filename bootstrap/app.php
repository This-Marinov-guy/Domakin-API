<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();

        $middleware->api(prepend: [
            \App\Http\Middleware\ProdFirewallMiddleware::class,
            \App\Http\Middleware\DemoConfigMiddleware::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\AxiomLoggerMiddleware::class,
        ]);

        $middleware->appendToGroup('api', [
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'auth.role' => \App\Http\Middleware\AuthorizationMiddleware::class,
            'domain.whitelist' => \App\Http\Middleware\DomainWhitelistMiddleware::class,
            'webhook.secret' => \App\Http\Middleware\VerifyWebhookSecret::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $e) {
            try {
                /** @var \App\Services\AxiomIngestService $axiom */
                $axiom = app(\App\Services\AxiomIngestService::class);

                if (!$axiom->isEnabled()) {
                    return false;
                }

                $exceptionPayload = new \App\Logging\Axiom\AxiomExceptionPayload(
                    message: $e->getMessage(),
                    code: $e->getCode() ?: null,
                    file: $e->getFile(),
                    line: $e->getLine(),
                );

                $event = \App\Logging\Axiom\AxiomErrorLog::make(
                    message: 'Unhandled exception: ' . get_class($e),
                    exception: $exceptionPayload,
                );

                $axiom->ingest($event);
            } catch (\Throwable) {
                // Never let Axiom reporting break the app
            }

            return false; // Let Laravel's default reporting continue
        });
    })->create();
