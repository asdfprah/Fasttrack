<?php

namespace Asdfprah\Fasttrack\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'categories';
    protected $guarded = [];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // Public, zero-param method that is NOT a relation — Mapper must ignore it.
    public function displayName(): string
    {
        return strtoupper($this->name ?? '');
    }
}
