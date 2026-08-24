<?php

return [
    'ignored_columns' => ['*.created_at', '*.updated_at', '*.deleted_at'],
    'exclude'     => ['api'],

    'schema_path' => storage_path('app/vifrost-schema.json'),
    'expose_schema_route' => false,
    'schema_route_path'   => 'api/_schema',
    'max_limit' => 100,
    'max_limit_per_model' => [
        // \App\Models\Category::class => null,
    ],

    'relations'   => [
        'hasMany',
        'hasOne',
        'belongsTo',
        'belongsToMany',
        'hasOneThrough',
        'hasManyThrough',
        'morphOne',
        'morphMany',
        'morphTo',
        'morphToMany',
        'morphedByMany'
    ],
];
