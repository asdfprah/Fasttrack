<?php

namespace Vifrost\Laravel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class PgProduct extends Model
{
    protected $table = 'pg_products';
    protected $connection = 'pgsql_test';
    protected $guarded = [];
}
