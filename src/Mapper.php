<?php

namespace Vifrost\Laravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileObject;
use Throwable;

class Mapper{

    protected $relationshipMap;
    private $relationMethods;

    public function __construct($models){
        $this->relationshipMap = [];
        $this->relationMethods = config('vifrost.relations');
        $this->mapModelsRelationships($models);
    }

    /**
     * Return the builded relationship map
     * @return array
     */
    public function getRelationshipMap(){
        return $this->relationshipMap;
    }

    /**
     * Map the relation of a model class
     * @param array $models
     * @return void
     */
    private function mapModelsRelationships(array $models){
        foreach($models as $model){
            $this->relationshipMap[$model] = $this->discoverRelations($model);
        }
    }

    /**
     * Discover a model's Eloquent relations the same way Laravel's own
     * `php artisan model:show` does (see Illuminate\Database\Eloquent\ModelInspector):
     * reflect over the model's own zero-parameter public methods, keep the ones
     * whose declared return type (or, absent that, whose source text) points to
     * a relation builder call, then invoke them and confirm the result actually
     * is an Eloquent Relation instance.
     *
     * @param string $model model full classname
     * @return \Vifrost\Laravel\Relation[]
     */
    private function discoverRelations(string $model):array{
        $instance = new $model;

        return collect(get_class_methods($model))
            ->map(fn($method) => new ReflectionMethod($model, $method))
            ->reject(fn(ReflectionMethod $method) =>
                $method->isStatic()
                || $method->isAbstract()
                || $method->getDeclaringClass()->getName() === Model::class
                || $method->getNumberOfParameters() > 0
            )
            ->filter(fn(ReflectionMethod $method) => $this->looksLikeRelation($method))
            ->map(function(ReflectionMethod $method) use ($model, $instance){
                try {
                    $relation = $method->invoke($instance);
                } catch (Throwable $e){
                    return null;
                }

                if(!$relation instanceof EloquentRelation){
                    return null;
                }

                // MorphTo has no single related model: it's resolved per-row from the
                // "*_type" column, so on a fresh instance getRelated() falls back to the
                // parent model itself. Report it as unknown instead of a misleading class.
                $related = $relation instanceof MorphTo ? null : get_class($relation->getRelated());

                return new Relation(
                    $model,
                    $related,
                    Str::afterLast(get_class($relation), '\\'),
                    $method->getName()
                );
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Cheap pre-filter used before actually invoking a candidate method: either
     * the method declares an Eloquent Relation return type, or its source text
     * calls one of the configured relation builder methods (hasMany, belongsTo, ...).
     *
     * @param \ReflectionMethod $method
     * @return bool
     */
    private function looksLikeRelation(ReflectionMethod $method):bool{
        $returnType = $method->getReturnType();
        if($returnType instanceof ReflectionNamedType
            && !$returnType->isBuiltin()
            && is_subclass_of($returnType->getName(), EloquentRelation::class)){
            return true;
        }

        if(is_null($method->getFileName())){
            return false;
        }

        $file = new SplFileObject($method->getFileName());
        $file->seek($method->getStartLine() - 1);
        $code = '';
        while($file->key() < $method->getEndLine()){
            $code .= trim($file->current());
            $file->next();
        }

        foreach($this->relationMethods as $relationMethod){
            if(str_contains($code, '$this->'.$relationMethod.'(')){
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a given model classname has a given relation
     *
     * @param string $model model classname
     * @param string $relationName name of the relation method
     * @return bool
     */
    public function modelHasRelationship(string $model, string $relationName){
        $relations = $this->relationshipMap[$model] ?? [];
        foreach ($relations as $relation) {
            if($relation->getRelationName() === $relationName){
                return true;
            }
        }
        return false;
    }
}
