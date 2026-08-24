<?php

use Vifrost\Laravel\Vifrost;
use Vifrost\Laravel\Tests\Fixtures\Category;
use Vifrost\Laravel\Tests\Fixtures\Product;
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

function vifrostQueryFor(string $path, array $query = [])
{
    app()->instance('request', Request::create('/'.$path, 'GET', $query));

    return (new Vifrost)->getQuery();
}

it('resolves a nested relation off an existing parent id', function () {
    $query = vifrostQueryFor('api/category/'.$this->category->id.'/products');

    expect($query)->toBeInstanceOf(Relation::class);
});

it('aborts with 404 when the parent id does not exist', function () {
    vifrostQueryFor('api/category/999999/products');
})->throws(NotFoundHttpException::class);

it('aborts with 404 when a relation is requested without an id first', function () {
    vifrostQueryFor('api/category/products');
})->throws(NotFoundHttpException::class);

it('resolves a flat show route to a query builder scoped by id', function () {
    $query = vifrostQueryFor('api/product/'.$this->product->id);

    expect($query)->toBeInstanceOf(Builder::class);
});

it('resolves a flat index route to an unscoped query builder', function () {
    $query = vifrostQueryFor('api/product');

    expect($query)->toBeInstanceOf(Builder::class);
});

it('resolves one record within a nested relation, scoped by its own id', function () {
    $query = vifrostQueryFor('api/category/'.$this->category->id.'/products/'.$this->product->id);

    // Relation::__call() returns $this (not a plain Builder) whenever the forwarded
    // query builder method — here, where() — returns the underlying query builder
    // itself, precisely so the result stays chainable/usable as a Relation.
    expect($query)->toBeInstanceOf(Relation::class);
});

it('aborts with 404 when a nested show id is not numeric', function () {
    vifrostQueryFor('api/category/'.$this->category->id.'/products/not-a-number');
})->throws(NotFoundHttpException::class);

it('aborts with 404 for a relation of a relation (more than one hop)', function () {
    vifrostQueryFor('api/category/'.$this->category->id.'/products/'.$this->product->id.'/comments');
})->throws(NotFoundHttpException::class);

describe('pagination', function () {
    beforeEach(function () {
        config(['vifrost.max_limit' => 100]);
        config(['vifrost.max_limit_per_model' => []]);

        // $this->category / $this->product (id 1) already exist from the outer
        // beforeEach; add a few more so there's something to actually slice.
        collect(range(2, 5))->each(fn ($n) => Product::create([
            'category_id' => $this->category->id,
            'name' => "Gadget {$n}",
            'price' => 9.99,
            'status' => 'active',
        ]));
    });

    it('applies the global max_limit by default on a flat index, with no ?limit= at all', function () {
        config(['vifrost.max_limit' => 3]);

        $query = vifrostQueryFor('api/product');

        expect($query->get())->toHaveCount(3);
    });

    it('honors an explicit ?limit= under the cap', function () {
        $query = vifrostQueryFor('api/product', ['limit' => 2]);

        expect($query->get())->toHaveCount(2);
    });

    it('clamps an explicit ?limit= over the cap down to the cap', function () {
        config(['vifrost.max_limit' => 3]);

        $query = vifrostQueryFor('api/product', ['limit' => 999]);

        expect($query->get())->toHaveCount(3);
    });

    it('shifts the result window with ?offset=', function () {
        $firstPage = vifrostQueryFor('api/product', ['limit' => 2, 'offset' => 0])->get();
        $secondPage = vifrostQueryFor('api/product', ['limit' => 2, 'offset' => 2])->get();

        expect($firstPage->pluck('id')->all())->not->toEqual($secondPage->pluck('id')->all());
    });

    it('paginates a nested relation collection the same way as a flat index', function () {
        $query = vifrostQueryFor('api/category/'.$this->category->id.'/products', ['limit' => 2]);

        expect($query->get())->toHaveCount(2);
    });

    it('returns everything unbounded for a model configured as exempt', function () {
        config(['vifrost.max_limit_per_model' => [Product::class => null]]);

        $query = vifrostQueryFor('api/product');

        expect($query->get())->toHaveCount(5);
    });

    it('never applies limit/offset to a single-record shape (show), even with ?limit= present', function () {
        $query = vifrostQueryFor('api/product/'.$this->product->id, ['limit' => 1]);

        $sql = strtolower($query->toSql());
        expect($sql)->not->toContain('limit')->not->toContain('offset');
    });
});
