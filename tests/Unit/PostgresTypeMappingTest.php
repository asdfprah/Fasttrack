<?php

use Asdfprah\Fasttrack\Describer;
use Asdfprah\Fasttrack\FormRequest\ValidationGenerator;
use Asdfprah\Fasttrack\Tests\Fixtures\PgProduct;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Postgres reports its own internal type-name aliases (int8, float8, bool, uuid, ...)
 * instead of the SQL-ish names MySQL/SQLite use, and formats defaults differently
 * ("'draft'::character varying", "nextval('...'::regclass)"). Skipped unless
 * PGSQL_TEST_HOST is set (see tests/TestCase.php and docker-compose.yml).
 */
beforeEach(function () {
    if (! getenv('PGSQL_TEST_HOST')) {
        $this->markTestSkipped('PGSQL_TEST_HOST not set, skipping Postgres-specific type mapping tests.');
    }

    Schema::connection('pgsql_test')->dropIfExists('pg_products');
    Schema::connection('pgsql_test')->create('pg_products', function (Blueprint $table) {
        $table->id();
        $table->string('name', 200);
        $table->float('rating')->nullable();
        $table->string('status', 20)->default('draft');
        $table->boolean('active')->default(true);
        $table->json('meta')->nullable();
        $table->uuid('external_id')->nullable();
        $table->smallInteger('rank')->nullable();
    });
});

it('maps Postgres native type aliases to the same rules as their MySQL/SQLite equivalents', function () {
    $description = Describer::describe(new PgProduct);

    expect($description['id']['type'])->toBe('int8')
        ->and($description['rating']['type'])->toBe('float8')
        ->and($description['active']['type'])->toBe('bool')
        ->and($description['external_id']['type'])->toBe('uuid');

    $generate = fn ($column) => (new ValidationGenerator)->generate($description[$column]);

    expect($generate('id'))->toBe('integer')
        ->and($generate('rating'))->toBe('numeric')
        ->and($generate('active'))->toBe('boolean')
        ->and($generate('external_id'))->toBe('uuid')
        ->and($generate('rank'))->toBe('integer');
});

it('does not report the auto-increment sequence expression as a real default value', function () {
    $description = Describer::describe(new PgProduct);

    expect($description['id']['hasDefaultValue'])->toBeFalse()
        ->and($description['id']['defaultValue'])->toBeNull()
        ->and($description['id']['hasAutoIncrement'])->toBeTrue();
});

it('strips the Postgres type cast and quoting from a string default', function () {
    $description = Describer::describe(new PgProduct);

    expect($description['status']['hasDefaultValue'])->toBeTrue()
        ->and($description['status']['defaultValue'])->toBe('draft');
});

it('still extracts string length from Postgres\' "character varying(N)" type text', function () {
    $description = Describer::describe(new PgProduct);

    expect($description['name']['length'])->toBe(200)
        ->and($description['status']['length'])->toBe(20);
});
