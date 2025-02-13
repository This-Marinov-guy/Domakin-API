<?php

    namespace App\Providers;

    use App\Services\GoogleServices\GoogleSheetsService;
    use Illuminate\Support\ServiceProvider;

    class GoogleSheetsServiceProvider extends ServiceProvider
    {
        public function register()
        {
            $this->app->singleton(GoogleSheetsService::class, function ($app) {
                return new GoogleSheetsService();
            });
        }
    }
