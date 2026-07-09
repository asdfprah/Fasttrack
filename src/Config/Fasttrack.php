<?php

return [
    'ignored_columns' => ['*.created_at', '*.updated_at', '*.deleted_at'],
    'exclude'     => ['api'],

    'schema_path' => storage_path('app/fasttrack-schema.json'),
    'expose_schema_route' => false,
    'schema_route_path'   => 'api/_schema',

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
