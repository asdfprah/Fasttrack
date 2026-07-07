<?php

use Asdfprah\Fasttrack\Mapper;
use Asdfprah\Fasttrack\Tests\Fixtures\Category;
use Asdfprah\Fasttrack\Tests\Fixtures\Comment;
use Asdfprah\Fasttrack\Tests\Fixtures\Product;

beforeEach(function () {
    createTestSchema();
    config()->set('fasttrack.relations', [
        'hasMany', 'hasOne', 'belongsTo', 'belongsToMany',
        'hasOneThrough', 'hasManyThrough', 'morphOne', 'morphMany',
        'morphTo', 'morphToMany', 'morphedByMany',
    ]);
});

function relationNames(array $relations): array
{
    return array_map(fn ($relation) => $relation->getRelationName(), $relations);
}

it('detects a hasMany relation declared with a typed return', function () {
    $map = (new Mapper([Category::class]))->getRelationshipMap();

    expect(relationNames($map[Category::class]))->toContain('products');

    $products = array_values(array_filter($map[Category::class], fn ($r) => $r->getRelationName() === 'products'));
    expect($products[0]->getRelationType())->toBe('HasMany')
        ->and($products[0]->getRelated())->toBe(Product::class);
});

it('ignores a public zero-parameter method that is not a relation', function () {
    $map = (new Mapper([Category::class]))->getRelationshipMap();

    expect(relationNames($map[Category::class]))->not->toContain('displayName');
});

it('detects an untyped belongsTo relation via the source fallback', function () {
    $map = (new Mapper([Product::class]))->getRelationshipMap();

    expect(relationNames($map[Product::class]))->toContain('category');
});

it('detects an untyped morphMany relation and resolves its related model', function () {
    $map = (new Mapper([Product::class]))->getRelationshipMap();

    $comments = array_values(array_filter($map[Product::class], fn ($r) => $r->getRelationName() === 'comments'));
    expect($comments)->toHaveCount(1)
        ->and($comments[0]->getRelationType())->toBe('MorphMany')
        ->and($comments[0]->getRelated())->toBe(Comment::class);
});

it('detects a morphTo relation without reporting a misleading related model', function () {
    $map = (new Mapper([Comment::class]))->getRelationshipMap();

    $commentable = array_values(array_filter($map[Comment::class], fn ($r) => $r->getRelationName() === 'commentable'));
    expect($commentable)->toHaveCount(1)
        ->and($commentable[0]->getRelationType())->toBe('MorphTo')
        ->and($commentable[0]->getRelated())->toBeNull();
});

it('modelHasRelationship reports true only for actual relations', function () {
    $mapper = new Mapper([Category::class]);

    expect($mapper->modelHasRelationship(Category::class, 'products'))->toBeTrue()
        ->and($mapper->modelHasRelationship(Category::class, 'displayName'))->toBeFalse();
});
