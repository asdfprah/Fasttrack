<?php

namespace Asdfprah\Fasttrack;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use ReflectionClass;

class Fasttrack{
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
     * Make a query based on the requested route, if a relation could not be resolved
     * abort the navigation with 404 error
     * 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getQuery(){
        $path = $this->getRequestPath();
        $query = (new ($this->guessModel( $path[ array_key_first($path) ] ) ) )->query() ;
        $path = array_slice($path , 1 , count($path) );
        foreach (  $path as $index => $section) {         
            if( !is_numeric( $section ) ){
                $query = is_a( get_class($query) , Relation::class , true) ? $this->resolveRelation($query->first() , $section) :  $this->resolveRelation($query , $section); 
                continue;
            }

            $query = $query->where('id' , $section);
            if( ($index +1 ) != ( count($path)  ) ){
                $query = $query->first();
            }
        }
        return $query;
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
     * Removes the fasttrack excluded path sections from a array
     * 
     * @param $path array of request url sections 
     * @return array
     */
    private function removeExcluded(array $path){
        $excluded = config('fasttrack.exclude');
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