<?php

use Asdfprah\Fasttrack\Describer;
use Asdfprah\Fasttrack\Tests\Fixtures\Product;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => createTestSchema());

it('describes primary key and auto increment', function () {
    $description = Describer::describe(new Product);

    expect($description['id']['isPrimaryKey'])->toBeTrue()
        ->and($description['id']['hasAutoIncrement'])->toBeTrue()
        ->and($description['id']['isNullable'])->toBeFalse();
});

it('describes a foreign key column', function () {
    $description = Describer::describe(new Product);

    expect($description['category_id']['isForeign'])->toBeTrue()
        ->and($description['category_id']['foreign']->foreignTable)->toBe('categories')
        ->and($description['category_id']['foreign']->foreignColumnName)->toBe(['id']);
});

it('extracts the length of string columns', function () {
    // SQLite doesn't preserve declared varchar length in its schema introspection at
    // all — "type" comes back as just "varchar", no "(200)". This is a genuine SQLite
    // limitation (the old Doctrine-based implementation had the same gap), not
    // something this package can recover. MySQL, MariaDB and Postgres all report the
    // real length (verified empirically against all three), just via different "type"
    // text — "varchar(200)" vs "character varying(200)" — which extractLength() already
    // handles for both.
    $description = Describer::describe(new Product);

    $isSqlite = DB::connection()->getDriverName() === 'sqlite';

    expect($description['name']['length'])->toBe($isSqlite ? null : 200)
        ->and($description['status']['length'])->toBe($isSqlite ? null : 20);
});

it('does not mistake decimal precision for a string length', function () {
    $description = Describer::describe(new Product);

    expect($description['price']['length'])->toBeNull();
});

it('reports nullable columns correctly', function () {
    $description = Describer::describe(new Product);

    expect($description['description']['isNullable'])->toBeTrue()
        ->and($description['name']['isNullable'])->toBeFalse();
});

it('reports default values', function () {
    $description = Describer::describe(new Product);

    expect($description['status']['hasDefaultValue'])->toBeTrue()
        ->and($description['status']['defaultValue'])->toBe('draft')
        ->and($description['name']['hasDefaultValue'])->toBeFalse();
});
