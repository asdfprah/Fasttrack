<?php

namespace Asdfprah\Fasttrack;

class SchemaExporter{

    /**
     * Build a JSON-serializable map of models — their table, primary key, column
     * attributes (as reported by Describer) and relations (as reported by Mapper).
     * Meant to be consumed by external tooling that generates typed Coloquent /
     * vue-api-query model adapters from it.
     *
     * @param array|null $models defaults to every model Fasttrack can find in the app
     * @return array
     */
    public static function build(?array $models = null):array{
        $models = $models ?? (new Fasttrack)->models()->all();

        $relationshipMap = (new Mapper($models))->getRelationshipMap();

        $schema = [];
        foreach ($models as $model) {
            $instance = new $model;
            $schema[$model] = [
                'table' => $instance->getTable(),
                'primaryKey' => $instance->getKeyName(),
                'attributes' => self::attributesFor($instance),
                'relations' => self::relationsFor($relationshipMap[$model] ?? []),
            ];
        }

        return $schema;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $instance
     * @return array
     */
    private static function attributesFor($instance):array{
        $attributes = [];
        foreach (Describer::describe($instance) as $column => $description) {
            $attributes[$column] = [
                'type' => $description['type'],
                'rawType' => $description['rawType'],
                'length' => $description['length'],
                'isNullable' => $description['isNullable'],
                'isPrimaryKey' => $description['isPrimaryKey'],
                'hasAutoIncrement' => $description['hasAutoIncrement'],
                'hasDefaultValue' => $description['hasDefaultValue'],
                'defaultValue' => $description['defaultValue'],
                'isForeign' => $description['isForeign'],
                'foreign' => self::foreignFor($description['foreign']),
            ];
        }
        return $attributes;
    }

    /**
     * @param \stdClass|null $foreign as returned by Describer::getForeignKeysFor()
     * @return array|null
     */
    private static function foreignFor($foreign){
        if(is_null($foreign)){
            return null;
        }
        return [
            'table' => $foreign->foreignTable,
            'column' => $foreign->foreignColumnName[0] ?? null,
        ];
    }

    /**
     * @param \Asdfprah\Fasttrack\Relation[] $relations
     * @return array
     */
    private static function relationsFor(array $relations):array{
        $result = [];
        foreach ($relations as $relation) {
            // MorphTo has no single fixed related model — it's resolved per-row from
            // the "*_type" column — so getRelated() is null; flag it explicitly rather
            // than let the adapter mistake the missing value for a mapping gap.
            $result[$relation->getRelationName()] = [
                'type' => $relation->getRelationType(),
                'related' => $relation->getRelated(),
                'polymorphic' => is_null($relation->getRelated()),
            ];
        }
        return $result;
    }
}
