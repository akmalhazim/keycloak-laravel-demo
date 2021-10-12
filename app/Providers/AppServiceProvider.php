<?php

namespace App\Providers;

use App\Keycloak\Keycloak;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(Keycloak::class, function () {
            return new Keycloak(config('services.keycloak.host'), config('services.keycloak.realm'), config('keycloak.services.client_id'));
        });
    }
}
