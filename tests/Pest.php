<?php

use Asdfprah\Fasttrack\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class)->in('Unit');

/**
 * Schema shared by the Describer/Mapper/Fasttrack tests: a Category hasMany Products,
 * a Product belongsTo Category and morphMany Comments, a Comment morphTo commentable.
 */
function createTestSchema(): void
{
    // Real DB servers (MySQL/MariaDB/Postgres) persist across test methods within the
    // same run — unlike SQLite's :memory:, which gets a fresh database per test since
    // Testbench boots a new application (and thus a new connection) each time. Drop
    // child tables before parents to avoid foreign key errors on re-creation.
    Schema::dropIfExists('comments');
    Schema::dropIfExists('products');
    Schema::dropIfExists('categories');

    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name', 120);
        $table->timestamps();
    });

    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->foreignId('category_id')->constrained();
        $table->string('name', 200);
        $table->decimal('price', 8, 2);
        $table->text('description')->nullable();
        $table->string('status', 20)->default('draft');
        $table->timestamps();
    });

    Schema::create('comments', function (Blueprint $table) {
        $table->id();
        $table->morphs('commentable');
        $table->string('body');
        $table->timestamps();
    });
}
