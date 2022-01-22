<?php

namespace Asdfprah\Fasttrack;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use stdClass;

class Describer{
    /**
     * Returns the type of a table column
     * 
     * @param string $connection database connection name
     * @param string $table database table name
     * @param string $column database table column
     * @return \Doctrine\DBAL\Schema\Column
     */
    private static function getColumn( string $connection ,string $table, string $column  ){
        return Schema::connection( $connection )->getConnection()->getDoctrineColumn($table, $column);
    }

    /**
     * Search foreign keys for a given database column
     * @param string $connection database connection name
     * @param string $table database table name
     * @param string $column database table column
     * 
     * @return mixed
     */
    public static function getForeignKeysFor( string $connection, string $table , string $column){
        $foreignKeys = self::getForeignKeys( $connection, $table );
        foreach ($foreignKeys as $foreign) {
            if( in_array( $column , $foreign->localColumn ) ){
                return $foreign;
            }
        }
        return null;
    }

    /**
     * Retrieves all the foreign keys of a given database table
     * 
     * @param string $connection database connection name
     * @param string $table database table name
     * @return array
     */
    public static function getForeignKeys( string $connection, string $table ){
        $foreignDescription =  [];
        $foreignList = Schema::connection( $connection )->getConnection()->getDoctrineSchemaManager()->listTableForeignKeys($table);
        foreach ($foreignList as $foreign) {
            $fk = new stdClass();
            $fk->localColumn = $foreign->getLocalColumns();
            $fk->foreignTable = $foreign->getForeignTableName();
            $fk->foreignColumnName = $foreign->getForeignColumns();
            array_push( $foreignDescription ,$fk );
        }
        return $foreignDescription;
    }

    /**
     * Returns an array with the columns of a given table
     * 
     * @param string $tableName database table name
     * @return array 
     */
    public static function getTableColumns(  string $connection , string $tableName ):array{
        return Schema::connection( $connection )->getColumnListing( $tableName );
    }


    /**
     * Returns the type of a table column
     * 
     * @param string $connection database connection name
     * @param string $table database table name
     * @param string $column database table column
     * @return string
     */
    public static function getTableColumnType( string $connection ,string $table, string $column ):string{
        return self::getColumn($connection, $table, $column)->getType()->getName();
    }

    /**
     * Check if a givel column of a table of a given connection is nullable
     * 
     * @param string $connection database connection name
     * @param string $table database table name
     * @param string $column database table column
     * @return boolean
     */
    public static function isColumnNullable( string $connection ,string $table, string $column ){
        return self::getColumn($connection, $table, $column)->getNotnull();
    }

    /**
     * Return the length of a given column
     * 
     * @param string $connection database connection name
     * @param string $table database table name
     * @param string $column database table column
     * @return mixed
     */
    public static function getColumnLength( string $connection ,string $table, string $column ){
        return self::getColumn($connection, $table, $column)->getLength();
    }

    /**
     * Return the default value of a given column
     * 
     * @param string $connection database connection name
     * @param string $table database table name
     * @param string $column database table column
     * @return mixed
     */
    public static function getDefaultValue( string $connection ,string $table, string $column ){
        return self::getColumn($connection, $table, $column)->getDefault();
    }

    /**
     * Check if a given column has an autoincrement
     * 
     * @param string $connection database connection name
     * @param string $table database table name
     * @param string $column database table column
     * @return boolean
     */
    public static function hasAutoIncrement( string $connection ,string $table, string $column ){
        return self::getColumn($connection, $table, $column)->getAutoincrement();
    }

    /**
     * Generates a description of the table of a given model
     * 
     * @param Model $model Model wich table is gonna be described
     * @return array
     */
    public static function describe(Model $model){
        $description = [];
        $connection = $model->getConnection()->getName();
        $table = $model->getTable();
        $columns = self::getTableColumns( $connection , $table );
        $primaryKey = $model->getKeyName();
        foreach ($columns as $column) {
            $foreign = self::getForeignKeysFor( $connection, $table, $column );
            $description[$column] = [
                'type' => self::getTableColumnType($connection , $table, $column),
                'isPrimaryKey' => $column == $primaryKey,
                'isForeign' => !is_null($foreign),
                'foreign' => $foreign,
                'isNullable' => self::isColumnNullable( $connection , $table, $column),
                'length' => self::getColumnLength( $connection , $table, $column),
                'hasDefaultValue' => !is_null(self::getDefaultValue( $connection, $table, $column)),
                'defaultValue' => self::getDefaultValue( $connection, $table, $column),
                'hasAutoIncrement' => self::hasAutoIncrement( $connection, $table, $column),
            ];
        }
        return $description;
    }
}