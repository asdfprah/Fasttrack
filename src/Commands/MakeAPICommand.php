<?php

namespace Vifrost\Laravel\Commands;

use Vifrost\Laravel\Vifrost;
use Vifrost\Laravel\Mapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MakeAPICommand extends Command
{
    /**
     * Colelction of working models
     * 
     * @var \Illuminate\Support\Collection
     */
    protected $models;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vifrost:api {model=all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates routes, request and controller for a given model';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->models = (new Vifrost)->models();
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $models = $this->getModels();

        foreach ($models as $model) {
            $exploded = explode('\\',  $model);

            $shortName = end( $exploded );

            Artisan::call("vifrost:request Store{$shortName}Request {$shortName}");

            Artisan::call("vifrost:request Update{$shortName}Request {$shortName}");

            Artisan::call("vifrost:controller {$shortName}");

            $path = base_path('routes/api.php');

            $routeSubPath = strtolower($shortName);

            $routes = $this->buildFlatRoutes($shortName, $routeSubPath);
            $routes .= $this->buildNestedRoutes($model, $routeSubPath);

            file_put_contents($path , $routes,  FILE_APPEND | LOCK_EX);

        }

        return 0;
    }

    /**
     * Flat CRUD routes for a model's own resource.
     *
     * @param string $shortName model class basename, e.g. "Product"
     * @param string $routeSubPath lowercased model name used as the URL segment
     * @return string
     */
    protected function buildFlatRoutes(string $shortName, string $routeSubPath):string{
        return "\r\n\r\nRoute::controller( '\\App\\Http\\Controllers\\{$shortName}Controller' )->group( function(){\r\n"
            ."    Route::get('{$routeSubPath}' , 'index' );\r\n"
            ."    Route::get('{$routeSubPath}/{id}' , 'show');\r\n"
            ."    Route::post('{$routeSubPath}', 'store');\r\n"
            ."    Route::put('{$routeSubPath}/{id}', 'update');\r\n"
            ."    Route::delete('{$routeSubPath}/{id}', 'destroy');\r\n"
            ."});";
    }

    /**
     * Read-only routes for each of the model's relations, exactly one hop deep: the
     * related model's own controller serves them, scoped by the parent's id (see
     * Vifrost::getQuery()). A relation of a relation isn't routed here at all —
     * that data is reachable in a single request via Spatie's dotted "include" query
     * param instead. MorphTo relations are skipped: they have no single fixed related
     * model to route to.
     *
     * @param string $model model full classname
     * @param string $routeSubPath lowercased model name used as the URL segment
     * @return string
     */
    protected function buildNestedRoutes(string $model, string $routeSubPath):string{
        $relations = (new Mapper([$model]))->getRelationshipMap()[$model] ?? [];
        $routes = '';

        foreach ($relations as $relation) {
            $related = $relation->getRelated();
            if(is_null($related)){
                continue;
            }

            $relatedExploded = explode('\\', $related);
            $relatedShortName = end( $relatedExploded );
            $relationName = $relation->getRelationName();

            $routes .= "\r\nRoute::get('{$routeSubPath}/{id}/{$relationName}', [\\App\\Http\\Controllers\\{$relatedShortName}Controller::class, 'index']);";
            $routes .= "\r\nRoute::get('{$routeSubPath}/{id}/{$relationName}/{childId}', [\\App\\Http\\Controllers\\{$relatedShortName}Controller::class, 'show']);";
        }

        return $routes;
    }

    public function getModels(){
        $input = $this->argument('model');
        return $input == 'all' ? $this->models : ["\\App\\Models\\$input"];
    }

}
