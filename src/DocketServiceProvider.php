<?php

namespace Merciall\Docket;

use Illuminate\Support\ServiceProvider;

class DocketServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        
        $this->mergeConfigFrom(
            $this->getConfigFile(),
            'config'
        );
    }

    public function register()
    {
        $this->app->make('Merciall\Docket\Docket');
    }

    protected function getConfigFile(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'docket.php';
    }
}
