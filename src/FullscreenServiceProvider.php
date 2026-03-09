<?php

namespace KevinBatdorf\Fullscreen;

use Illuminate\Support\ServiceProvider;
use KevinBatdorf\Fullscreen\Commands\CopyAssetsCommand;

class FullscreenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Fullscreen::class, function () {
            return new Fullscreen;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CopyAssetsCommand::class,
            ]);
        }
    }
}
