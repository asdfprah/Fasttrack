<?php

namespace Asdfprah\Fasttrack;

use Illuminate\Support\Facades\File;
use TRegx\CleanRegex\Pattern;
use ReflectionClass;

class Mapper{

    protected $models;
    protected $relationships;
    private $relationshipMap;

    public function getRelationshipMap(){
        return $this->relationshipMap;
    }

    public function __construct($models){
        $this->relationshipMap = [];
        $this->relationships = config('fasttrack.relations');
        $this->models = (new Fasttrack)->models();
        $this->mapModelsRelationships($models);
    }

    private function mapModelsRelationships($models){
        foreach($models as $model){
            $this->mapRelationshipsForModel($model);
        }
    }

    private function mapRelationshipsForModel($model){
        $reflection = new ReflectionClass( $model );
        $fileContent = str_replace(["\n", "\r", "\t"], " ", File::get( $reflection->getFileName() ));
        $otherModels = $this->models->filter(function($item) use ($model){
            return $item !== $model;
        });

        $modelRelationships = [];
        foreach ($otherModels as $modelToCompare) {
            foreach($this->relationships as $relationType){
                $matches = $this->patternBuilder($relationType , $modelToCompare)->match($fileContent)->all();
                
                $matches = array_filter($matches, function($match){
                    return strlen($match) !== 0;
                });
                $relations = array_map(function($relationName) use ($model, $modelToCompare, $relationType){
                    return new Relation($model, $modelToCompare, $relationType, $relationName);
                }, $matches);

                $matchesWithClass = $this->patternBuilder($relationType , $modelToCompare, true)->match($fileContent)->all();

                $matchesWithClass = array_filter($matchesWithClass, function($match){
                    return strlen($match) !== 0;
                });
                $relationsWithClass = array_map(function($relationName) use ($model, $modelToCompare, $relationType){
                    return new Relation($model, $modelToCompare, $relationType, $relationName);
                }, $matchesWithClass);

                $relations = array_merge( $relations , $relationsWithClass );

                if(count($relations) == 0){
                    continue;
                }
                $modelRelationships = array_merge( $modelRelationships , $relations );
            }
        }

        $this->relationshipMap[$model] = $modelRelationships;
    }

    private function patternBuilder($relationName, $model, $shortName = false){
        if($shortName){
            $exploded = explode('\\', $model);
            $model = end($exploded);
            $model = $model.'::class';
        }else{
            $model = addslashes($model);
            $model = substr( $model, 2 , strlen($model) );
            $model = "'".$model."'";
        }
        return Pattern::of("\w*(?= *\( *\) *\{.*".$relationName."\( *\t*".$model.")");
    }

    public function modelHasRelationship($model, $relationName){
        $relations = $this->relationshipMap[$model];
        foreach ($relations as $relation) {
            if($relation->getRelationName() === $relationName){
                return true;
            }
        }
        return false;
    }
}