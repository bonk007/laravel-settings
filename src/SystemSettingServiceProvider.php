<?php

namespace Settings;

use Illuminate\Support\ServiceProvider;

class SystemSettingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Manager::class, function ($app) {
            return new Manager($app->make('db.connection'), $app->make('cache'));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(dirname(__DIR__) . '/migrations');
        }
    }
}
