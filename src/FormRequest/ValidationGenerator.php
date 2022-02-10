<?php

namespace Asdfprah\Fasttrack\FormRequest;

class ValidationGenerator{
    private string $rules;

    public function __construct(){
        $this->rules = "";
    }
    
    private function addRule(string $rule){
        strlen($this->rules) == 0 ? $this->rules = $this->rules.$rule : $this->rules =$this->rules."|{$rule}";
    }

    public function generate($description){
        $this->addRequiredRule( $description["isNullable"] || $description["hasDefaultValue"] || $description["hasAutoIncrement"] );
        $this->addTypeRule($description["type"]);
        $this->addLengthRule( $description["length"] );
        if($description["isForeign"]){
            $this->addExistsRule($description["foreign"]);
        }
        return $this->rules;
    }

    private function addExistsRule($foreign){
        $table = $foreign->foreignTable;
        $column = $foreign->foreignColumnName[0];
        $this->addRule("exists:{$table},{$column}");
    }

    private function addLengthRule($length){
        $length = $length ?? 0;
        if($length == 0){
            return;
        }
        $this->addRule("max:{$length}");
    }

    private function addRequiredRule( bool $isRequired ){
        if(!$isRequired){
            return;
        }
        $this->addRule("required");
    }

    private function addTypeRule(string $type){
        switch ($type) {
            case 'bigint':
                $this->addRule("integer");
                break;
            case 'binary':
                break;
            case 'blob':
                break;
            case 'boolean':
                $this->addRule("boolean");
                break;
            case 'date':
                $this->addRule("date");
                break;
            case 'datetime':
                $this->addRule("date_format:Y-m-d H:i:s");
                break;
            case 'decimal':
                $this->addRule("numeric");
                break;
            case 'float':
                $this->addRule("numeric");
                break;
            case 'integer':
                $this->addRule("integer");
                break;
            case 'simple_array':
                break;
            case 'smallint':
                $this->addRule("integer");
                break;
            case 'string':
                $this->addRule("string");
                break;
            case 'text':
                $this->addRule("string");
                break;
            case 'time':
                $this->addRule("date_format:H:i");
                break;
        }
    }
}