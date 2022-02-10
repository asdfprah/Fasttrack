<?php

namespace Asdfprah\Fasttrack;

use Illuminate\Support\Facades\File;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use ReflectionClass;

class Fasttrack{
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
    
    public function models():Collection{
        return $this->findByClass(Model::class);
    }
  
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

    public function getRequestPath(){
        $sections = explode("/", request()->path()  );
        array_map('strtolower', $sections);
        return array_values( $this->removeExcluded( $sections ) ) ;
    }

    private function removeExcluded($path){
        $excluded = config('fasttrack.exclude');
        return array_filter( $path, function( $subSection ) use ($excluded) {
            return ! in_array( $subSection , $excluded );
        } );  
    }

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
        return $match;
    }

    public function updateCache( $models = null ){
        if( Cache::has('fasttrack') ){
            Cache::forget( 'fasttrack' );
        }
        $models = is_null( $models ) ? $this->models() : $models;
        $mapper = new Mapper( $models );
        Cache::forever( 'fasttrack' , $mapper->getRelationshipMap() );
    }

    private function resolveRelation($subject , $relation){
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