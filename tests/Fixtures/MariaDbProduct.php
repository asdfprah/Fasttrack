<?php

namespace Asdfprah\Fasttrack\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class MariaDbProduct extends Model
{
    protected $table = 'mariadb_products';
    protected $connection = 'mariadb_test';
    protected $guarded = [];
}
