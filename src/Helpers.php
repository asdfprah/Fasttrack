<?php

namespace Asdfprah\Fasttrack;

use Illuminate\Support\Facades\File;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;

class Helpers{
    public static function findByClass($className, $returnFiles = false):Collection {
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
    
    public static function models():Collection{
        return Helpers::findByClass(Model::class);
    }
}