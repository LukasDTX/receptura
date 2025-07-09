<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ZlecenieService;
use App\Services\SurowceCalculatorService;
use App\Services\ZlecenieFormService;
use App\Services\MagazynSurowcowService;

class ZlecenieServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Rejestracja MagazynSurowcowService (jeśli jeszcze nie jest zarejestrowany)
        $this->app->singleton(MagazynSurowcowService::class, function ($app) {
            return new MagazynSurowcowService();
        });

        // Rejestracja SurowceCalculatorService
        $this->app->singleton(SurowceCalculatorService::class, function ($app) {
            return new SurowceCalculatorService();
        });

        // Rejestracja ZlecenieService z dependency injection
        $this->app->singleton(ZlecenieService::class, function ($app) {
            return new ZlecenieService(
                $app->make(MagazynSurowcowService::class),
                $app->make(SurowceCalculatorService::class)
            );
        });

        // Rejestracja ZlecenieFormService z dependency injection
        $this->app->singleton(ZlecenieFormService::class, function ($app) {
            return new ZlecenieFormService(
                $app->make(ZlecenieService::class),
                $app->make(SurowceCalculatorService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}