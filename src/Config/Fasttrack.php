<?php

return [

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