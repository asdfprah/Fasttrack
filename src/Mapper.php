<?php

namespace Asdfprah\Fasttrack;

use Illuminate\Support\Facades\File;
use TRegx\CleanRegex\Match\Details\Detail;
use TRegx\CleanRegex\Pattern;
use ReflectionClass;
use Illuminate\Support\Collection;
class Mapper{

    protected $models;
    protected $relationships;
    private $relationshipMap;

    public function __construct($models){
        $this->relationshipMap = [];
        $this->relationships = config('fasttrack.relations');
        $this->models = (new Fasttrack)->models();
        $this->mapModelsRelationships($models);
    }

    /**
     * Return the builded relationship map
     * @return array
     * @return void
     */
    public function getRelationshipMap(){
        return $this->relationshipMap;
    }

    /**
     * Map the relation of a model class
     * @param \Illuminate\Support\Collection $models
     * @return void
     */
    private function mapModelsRelationships(Collection $models){
        foreach($models as $model){
            $this->mapRelationshipsForModel($model);
        }
    }

    /**
     * Map the relations for a given model
     * @param string $model model full classname
     */
    private function mapRelationshipsForModel(string $model){
        $reflection = new ReflectionClass( $model );
        $fileContent = str_replace(["\n", "\r", "\t"], " ", File::get( $reflection->getFileName() ));
        $otherModels = $this->models->filter(function($item) use ($model){
            return $item !== $model;
        });

        $modelRelationships = [];
        foreach ($otherModels as $modelToCompare) {
            foreach($this->relationships as $relationType){
                $relations = $this->patternBuilder($relationType , $modelToCompare)->match($fileContent)
                    ->stream()
                    ->filter(function (Detail $detail) {
                        return $detail->textLength() > 0;
                    })
                    ->map(function (string $relationName) use ($model, $modelToCompare, $relationType) {
                        return new Relation($model, $modelToCompare, $relationType, $relationName);
                    })
                    ->all();

                $relationsWithClass = $this->patternBuilder($relationType , $modelToCompare, true)->match($fileContent)
                    ->stream()
                    ->filter(function (Detail $detail) {
                        return $detail->textLength() > 0;
                    })
                    ->map(function(string $relationName) use ($model, $modelToCompare, $relationType){
                        return new Relation($model, $modelToCompare, $relationType, $relationName);
                    })
                    ->all();

                $relations = array_merge( $relations , $relationsWithClass );

                if(count($relations) == 0){
                    continue;
                }
                $modelRelationships = array_merge( $modelRelationships , $relations );
            }
        }

        $this->relationshipMap[$model] = $modelRelationships;
    }

    /**
     * Build a regexp pattern to match a given a relation
     * 
     * @param string $relationName name of the relation Eg: hasMany
     * @param string $model model classname
     * @param bool $shortName Indicates if the pattern uses the ::class shorthand
     * @return \TRegx\CleanRegex\Pattern
     */
    private function patternBuilder(string $relationName, string $model, bool $shortName = false){
        if($shortName){
            $exploded = explode('\\', $model);
            $model = end($exploded);
            $model = $model.'::class';
        }else{
            $model = addslashes($model);
            $model = substr( $model, 2 , strlen($model) );
            $model = "'".$model."'";
        }
        return Pattern::inject("\w*(?= *\( *\) *\{.*@\( *\t*@)", [$relationName, $model]);
    }

    /**
     * Check if a given model classname has a given relatiom
     * 
     * @param string $model model classname
     * @param string $relationName name of the relation method
     * @return bool 
     */
    public function modelHasRelationship(string $model, string $relationName){
        $relations = $this->relationshipMap[$model];
        foreach ($relations as $relation) {
            if($relation->getRelationName() === $relationName){
                return true;
            }
        }
        return false;
    }
}
