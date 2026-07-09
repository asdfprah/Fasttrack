<?php

use Asdfprah\Fasttrack\SchemaExporter;
use Asdfprah\Fasttrack\Tests\Fixtures\Category;
use Asdfprah\Fasttrack\Tests\Fixtures\Comment;
use Asdfprah\Fasttrack\Tests\Fixtures\Product;

beforeEach(fn () => createTestSchema());

it('builds a table/primaryKey/attributes/relations entry per model, keyed by FQCN', function () {
    $schema = SchemaExporter::build([Category::class, Product::class, Comment::class]);

    expect($schema)->toHaveKeys([Category::class, Product::class, Comment::class])
        ->and($schema[Product::class]['table'])->toBe('products')
        ->and($schema[Product::class]['primaryKey'])->toBe('id');
});

it('includes the same column attributes Describer reports, plus a clean foreign key shape', function () {
    $schema = SchemaExporter::build([Category::class, Product::class]);

    $categoryId = $schema[Product::class]['attributes']['category_id'];

    expect($categoryId['isForeign'])->toBeTrue()
        ->and($categoryId['foreign'])->toBe(['table' => 'categories', 'column' => 'id'])
        ->and($schema[Product::class]['attributes']['status']['defaultValue'])->toBe('draft');
});

it('lists relations by name with their Eloquent relation type and related model', function () {
    $schema = SchemaExporter::build([Category::class, Product::class]);

    expect($schema[Category::class]['relations']['products'])->toBe([
        'type' => 'HasMany',
        'related' => Product::class,
        'polymorphic' => false,
    ])->and($schema[Product::class]['relations']['category'])->toBe([
        'type' => 'BelongsTo',
        'related' => Category::class,
        'polymorphic' => false,
    ]);
});

it('flags a MorphTo relation as polymorphic instead of reporting a misleading related model', function () {
    $schema = SchemaExporter::build([Comment::class]);

    expect($schema[Comment::class]['relations']['commentable'])->toBe([
        'type' => 'MorphTo',
        'related' => null,
        'polymorphic' => true,
    ]);
});
