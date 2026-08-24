<?php

use Vifrost\Laravel\SchemaExporter;
use Vifrost\Laravel\Tests\Fixtures\Category;
use Vifrost\Laravel\Tests\Fixtures\Comment;
use Vifrost\Laravel\Tests\Fixtures\Product;

beforeEach(fn () => createTestSchema());

it('includes a schemaVersion so external tooling can detect a contract change', function () {
    $schema = SchemaExporter::build([Product::class]);

    expect($schema['schemaVersion'])->toBe(SchemaExporter::SCHEMA_VERSION);
});

it('builds a table/primaryKey/attributes/relations entry per model, keyed by FQCN', function () {
    $schema = SchemaExporter::build([Category::class, Product::class, Comment::class]);

    expect($schema['models'])->toHaveKeys([Category::class, Product::class, Comment::class])
        ->and($schema['models'][Product::class]['table'])->toBe('products')
        ->and($schema['models'][Product::class]['primaryKey'])->toBe('id');
});

it('exposes the actual routed URL segment, not the (possibly different) DB table name', function () {
    $schema = SchemaExporter::build([Product::class]);

    expect($schema['models'][Product::class]['resource'])->toBe('product');
});

it('exposes the effective maxLimit per model, reflecting config', function () {
    config(['vifrost.max_limit' => 50]);
    config(['vifrost.max_limit_per_model' => [Category::class => null]]);

    $schema = SchemaExporter::build([Category::class, Product::class]);

    expect($schema['models'][Product::class]['maxLimit'])->toBe(50)
        ->and($schema['models'][Category::class]['maxLimit'])->toBeNull();
});

it('includes the same column attributes Describer reports, plus a clean foreign key shape', function () {
    $schema = SchemaExporter::build([Category::class, Product::class]);

    $categoryId = $schema['models'][Product::class]['attributes']['category_id'];

    expect($categoryId['isForeign'])->toBeTrue()
        ->and($categoryId['foreign'])->toBe(['table' => 'categories', 'column' => 'id'])
        ->and($schema['models'][Product::class]['attributes']['status']['defaultValue'])->toBe('draft');
});

it('lists relations by name with their Eloquent relation type and related model', function () {
    $schema = SchemaExporter::build([Category::class, Product::class]);

    expect($schema['models'][Category::class]['relations']['products'])->toBe([
        'type' => 'HasMany',
        'related' => Product::class,
        'polymorphic' => false,
    ])->and($schema['models'][Product::class]['relations']['category'])->toBe([
        'type' => 'BelongsTo',
        'related' => Category::class,
        'polymorphic' => false,
    ]);
});

it('flags a MorphTo relation as polymorphic instead of reporting a misleading related model', function () {
    $schema = SchemaExporter::build([Comment::class]);

    expect($schema['models'][Comment::class]['relations']['commentable'])->toBe([
        'type' => 'MorphTo',
        'related' => null,
        'polymorphic' => true,
    ]);
});
