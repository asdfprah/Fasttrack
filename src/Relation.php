<?php

namespace Asdfprah\Fasttrack;

class Relation{

    private $model;
    private $related;
    private $relationType;
    private $relationName;

    public function __construct($model, $related, $relationType, $relationName){
        $this->model = $model;
        $this->related = $related;
        $this->relationType = $relationType;
        $this->relationName = $relationName;
    }

    public function getModel(){
        return $this->model;
    }

    public function getRelated(){
        return $this->related;
    }

    public function getRelationType(){
        return $this->relationType;
    }

    public function getRelationName(){
        return $this->relationName;
    }
}