<?php

namespace Vifrost\Laravel;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;

class Vifrost{
    /**
     * Find all classes declared of a given type inside the Laravel app folder
     * 
     * @param string $className class type to find
     * @param boolean $returnFiles=false indicated if the method should return the files. If false it only return the full class name
     * @return \Illuminate\Support\Collection
     */
    public function findByClass($className, $returnFiles = false):Collection {
        $classes = collect(File::allFiles(app_path()))
            ->map(function ($item) {
                $path = $item->getRelativePathName();
                $class = sprintf('\%s%s',
                    Container::getInstance()->getNamespace(),
                    strtr(substr($path, 0, strrpos($path, '.')), '/', '\\'));
    
                return $class;
            })
            ->filter(function ($class) use ($className) {
                $valid = false;
    
                if (class_exists($class)) {
                    $reflection = new ReflectionClass($class);
                    $valid = $reflection->isSubclassOf($className) &&
                        !$reflection->isAbstract();
                }
                return $valid;
            });
        
        return $returnFiles ? $classes->map(function($item){
            return new File( ( new ReflectionClass($item) )->getFileName() );
        }) : $classes->values();
    }
    
    /**
     * Return a collection of all existent models inside the Laravel app folder
     * 
     * @return \Illuminate\Support\Collection
     */
    public function models():Collection{
        return $this->findByClass(Model::class);
    }
  

    /**
     * Make a query based on the requested route. Only four shapes are supported:
     *   {model}                          -> index, the whole table
     *   {model}/{id}                      -> show, one record
     *   {model}/{id}/{relation}           -> the relation's collection, as the primary resource
     *   {model}/{id}/{relation}/{childId} -> one record within that relation
     *
     * A relation of a relation (e.g. {model}/{id}/{relation}/{childId}/{relation}) is
     * intentionally not supported: that data is already reachable in a single request
     * through Spatie's dotted "include" query param (?include=relation.nested), which
     * doesn't require the URL itself to nest. Anything deeper than one relation hop
     * aborts with 404, as does a relation requested off a record that doesn't exist.
     *
     * The two collection shapes (index, nested collection) go through
     * Pagination::resolveLimit()/resolveOffset() — see paginate() below — the two
     * single-record shapes never do.
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation
     */
    public function getQuery(){
        $path = $this->getRequestPath();
        if( count($path) > 4 ){
            abort(404);
        }

        $rootModel = $this->guessModel($path[0]);
        $query = (new $rootModel)->query();

        if( count($path) === 1 ){
            return $this->paginate($query, $rootModel);
        }

        if( !is_numeric($path[1]) ){
            abort(404);
        }
        $query = $query->where('id', $path[1]);

        if( count($path) === 2 ){
            return $query;
        }

        $model = $query->first();
        if( is_null($model) ){
            abort(404);
        }

        $relation = $this->resolveRelation($model, $path[2]);

        if( count($path) === 3 ){
            return $this->paginate($relation, get_class($relation->getRelated()));
        }

        if( !is_numeric($path[3]) ){
            abort(404);
        }
        return $relation->where('id', $path[3]);
    }

    /**
     * Applies the configured limit/offset for $modelClass (see Pagination) to a
     * collection query — the index and nested-relation-collection shapes only.
     * Single-record lookups (show, nested show) never go through here.
     *
     * offset() is only ever applied alongside a real limit(): an OFFSET clause
     * with no LIMIT is invalid SQL on SQLite (and MySQL), so a model exempt from
     * pagination with no ?limit= given returns everything, ignoring ?offset= —
     * the client can still get an offset from an exempt model by also passing
     * ?limit= (Pagination::resolveLimit() honors an explicit one either way).
     *
     * @param mixed $query
     * @param string $modelClass model full classname
     * @return mixed
     */
    private function paginate($query, string $modelClass){
        $limit = Pagination::resolveLimit($modelClass);

        if( is_null($limit) ){
            return $query;
        }

        return $query->limit($limit)->offset(Pagination::resolveOffset());
    }

    /**
     * Retrieves the request path removing the configured excluded sections
     * 
     * @return array
     */
    private function getRequestPath(){
        $sections = explode("/", request()->path()  );
        array_map('strtolower', $sections);
        return array_values( $this->removeExcluded( $sections ) ) ;
    }

    /**
     * Removes the vifrost excluded path sections from a array
     * 
     * @param $path array of request url sections 
     * @return array
     */
    private function removeExcluded(array $path){
        $excluded = config('vifrost.exclude');
        return array_filter( $path, function( $subSection ) use ($excluded) {
            return ! in_array( $subSection , $excluded );
        } );  
    }

    /**
     * Guess the model based on a string with the model name
     * 
     * @return string matched model full class name
     */
    private function guessModel($name){
        $models = $this->models();
        $match = null;
        foreach ($models as $model) {
            $exploded = explode("\\", $model);
            $className = strtolower( end( $exploded ) );
            if( $className === strtolower( $name ) ){
                $match = $model;
                break;
            }
        }
        if(is_null($match)){
            throw new Exception("Could not find a matching model for $name", 404);
        }
        return $match;
    }

    /**
     * Resolves a relation for a given subject
     * 
     * @param \Illuminate\Database\Eloquent\Model $subject where the relation is searched
     * @param string $relation method name for the relation
     * @return \Illuminate\Database\Eloquent\Builder;
     */
    private function resolveRelation(Model $subject , string $relation){
        if( method_exists($subject, $relation) ){
            return $subject->$relation();
        }
        $plural = Str::plural( $relation );
        if( method_exists($subject , $plural) ){
            return $subject->$plural();
        }
        $singular = Str::singular( $relation );
        if( method_exists($subject , $singular) ){
            return $subject->$singular();
        }
        abort(404);
    }
}