<?php

namespace App\Providers;

use App\Contracts\MissionStateSource;
use App\Services\FixtureMissionStateSource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MissionStateSource::class, FixtureMissionStateSource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
