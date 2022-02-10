<?php

namespace Asdfprah\Fasttrack\Providers;

use Asdfprah\Fasttrack\Commands\MakeAPICommand;
use Asdfprah\Fasttrack\Commands\MakeControllerCommand;
use Asdfprah\Fasttrack\Commands\MakeRequestCommand;
use Illuminate\Support\ServiceProvider;


class FasttrackServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            $this->getSrcPath().'/Config/Fasttrack.php', 'fasttrack'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeRequestCommand::class,
                MakeControllerCommand::class,
                MakeAPICommand::class
            ]);
        }

        $srcPath = $this->getSrcPath();
        $this->publishes([
            $srcPath.'/Config/Fasttrack.php' => $this->configPath('fasttrack.php')
        ], 'config');
    }

    private function getSrcPath(){
        return dirname( dirname(__FILE__) );
    }

    private function configPath($path = '')
    {
        return app()->basePath() . '/config' . ($path ? '/' . $path : $path);
    }
}
