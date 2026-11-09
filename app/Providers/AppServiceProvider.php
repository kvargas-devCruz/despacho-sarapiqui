<?php

namespace App\Providers;

use App\Services\Routing\OsrmRouteProvider;
use App\Services\Routing\RouteProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Servicio de rutas por carretera; se cambia de proveedor aquí
        $this->app->singleton(RouteProvider::class, OsrmRouteProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
