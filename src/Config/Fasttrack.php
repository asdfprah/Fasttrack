<?php

return [
    'ignored_columns' => ['*.created_at', '*.updated_at', '*.deleted_at'],
    'exclude'     => ['api'], 
    'relations'   => [ 
        'hasMany', 
        'hasOne', 
        'belongsTo', 
        'belongsToMany',
        'hasOneThrough', 
        'hasManyThrough', 
        'morphOne', 
        'morphMany', 
        'morphToMany', 
        'morphedByMany'
    ],
];