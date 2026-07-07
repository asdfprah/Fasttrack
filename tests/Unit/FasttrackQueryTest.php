<?php

use Asdfprah\Fasttrack\Fasttrack;
use Asdfprah\Fasttrack\Tests\Fixtures\Category;
use Asdfprah\Fasttrack\Tests\Fixtures\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    createTestSchema();

    $this->category = Category::create(['name' => 'Widgets']);
    $this->product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Gadget',
        'price' => 9.99,
        'status' => 'active',
    ]);
});

function fasttrackQueryFor(string $path)
{
    app()->instance('request', Request::create('/'.$path));

    return (new Fasttrack)->getQuery();
}

it('resolves a nested relation off an existing parent id', function () {
    $query = fasttrackQueryFor('api/category/'.$this->category->id.'/products');

    expect($query)->toBeInstanceOf(Relation::class);
});

it('aborts with 404 when the parent id does not exist', function () {
    fasttrackQueryFor('api/category/999999/products');
})->throws(NotFoundHttpException::class);

it('aborts with 404 when a relation is requested without an id first', function () {
    fasttrackQueryFor('api/category/products');
})->throws(NotFoundHttpException::class);

it('resolves a flat show route to a query builder scoped by id', function () {
    $query = fasttrackQueryFor('api/product/'.$this->product->id);

    expect($query)->toBeInstanceOf(Builder::class);
});

it('resolves a flat index route to an unscoped query builder', function () {
    $query = fasttrackQueryFor('api/product');

    expect($query)->toBeInstanceOf(Builder::class);
});

it('resolves one record within a nested relation, scoped by its own id', function () {
    $query = fasttrackQueryFor('api/category/'.$this->category->id.'/products/'.$this->product->id);

    // Relation::__call() returns $this (not a plain Builder) whenever the forwarded
    // query builder method — here, where() — returns the underlying query builder
    // itself, precisely so the result stays chainable/usable as a Relation.
    expect($query)->toBeInstanceOf(Relation::class);
});

it('aborts with 404 when a nested show id is not numeric', function () {
    fasttrackQueryFor('api/category/'.$this->category->id.'/products/not-a-number');
})->throws(NotFoundHttpException::class);

it('aborts with 404 for a relation of a relation (more than one hop)', function () {
    fasttrackQueryFor('api/category/'.$this->category->id.'/products/'.$this->product->id.'/comments');
})->throws(NotFoundHttpException::class);
