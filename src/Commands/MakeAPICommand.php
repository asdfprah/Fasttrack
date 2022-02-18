<?php

namespace Asdfprah\Fasttrack\Commands;

use Asdfprah\Fasttrack\Fasttrack;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MakeAPICommand extends Command
{
    /**
     * 
     * 
     */
    protected $models;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fasttrack:api {model?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->models = (new Fasttrack)->models();
        parent::__construct();
    }


    protected function updateCache(){
        (new Fasttrack)->updateCache( $this->models );
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //$this->updateCache();   //no lo estoy usando xD

        $models = $this->getModels();
        
        foreach ($models as $model) {
            $exploded = explode('\\',  $model);

            $shortName = end( $exploded );

            Artisan::call("fasttrack:request Store{$shortName}Request {$shortName}");

            Artisan::call("fasttrack:request Update{$shortName}Request {$shortName}");

            Artisan::call("fasttrack:controller {$shortName}");

            $path = base_path('routes/api.php');  

            $routeSubPath = strtolower($shortName);

            $routes = "\r\n\r\nRoute::controller( '\\App\\Http\\Controllers\\{$shortName}Controller' )->group( function(){\r\n    Route::get('{any}/{$routeSubPath}' , 'index' )->where('any','.*');\r\n    Route::get('{$routeSubPath}' , 'index' );\r\n    Route::get('{any}/{$routeSubPath}/{id}' , 'show')->where('any', '.*');\r\n    Route::get('{$routeSubPath}/{id}' , 'show');\r\n    Route::post('{$routeSubPath}', 'store');\r\n    Route::put('{$routeSubPath}/{id}', 'update');\r\n    Route::delete('{$routeSubPath}/{id}', 'destroy');\r\n});";

            file_put_contents($path , $routes,  FILE_APPEND | LOCK_EX);

        }

        return 0;
    }

    public function getModels(){
        $input = $this->argument('model');
        return $input == 'all' ? $this->models : ["\\App\\Models\\$input"];
    }

}
