<?php

use Vifrost\Laravel\Vifrost;
use Vifrost\Laravel\Pagination;
use Vifrost\Laravel\Tests\Fixtures\Category;
use Vifrost\Laravel\Tests\Fixtures\Product;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Regression coverage for Controller.stub's index(): Vifrost::getQuery() only resolves
 * and scopes the query (see VifrostQueryTest.php) — limit/offset and the X-Total-Count
 * total are entirely the generated controller's responsibility, moved there
 * deliberately so pagination is visible in a file the developer actually owns and reads,
 * instead of being hidden inside this package. This helper mirrors index()'s exact
 * logic, in the exact same order, so a change to one without the other shows up here.
 */
function controllerIndexFor(string $modelClass, string $path, array $query = [])
{
    app()->instance('request', Request::create('/' . $path, 'GET', $query));

    $resolvedQuery = (new Vifrost)->getQuery();

    $queryBuilder = QueryBuilder::for($resolvedQuery)
        ->allowedFilters([])
        ->allowedIncludes([])
        ->allowedSorts(['id']);

    $total = $queryBuilder->toBase()->getCountForPagination();

    $limit = Pagination::resolveLimit($modelClass);

    if (is_null($limit) && request()->has('offset')) {
        abort(422, '?offset= requires ?limit= to also be provided for this resource.');
    }

    if (! is_null($limit)) {
        $queryBuilder->limit($limit)->offset(Pagination::resolveOffset());
    }

    return [$queryBuilder->get(), $total];
}

beforeEach(function () {
    createTestSchema();

    config(['vifrost.max_limit' => 100]);
    config(['vifrost.max_limit_per_model' => []]);

    $this->category = Category::create(['name' => 'Widgets']);
    collect(range(1, 5))->each(fn($n) => Product::create([
        'category_id' => $this->category->id,
        'name' => "Gadget {$n}",
        'price' => 9.99,
        'status' => 'active',
    ]));
});

it('applies the global max_limit by default, with no ?limit= at all, and reports the real total', function () {
    config(['vifrost.max_limit' => 3]);

    [$rows, $total] = controllerIndexFor(Product::class, 'api/product');

    expect($rows)->toHaveCount(3);
    expect($total)->toBe(5);
});

it('honors an explicit ?limit= under the cap, and still reports the real total', function () {
    [$rows, $total] = controllerIndexFor(Product::class, 'api/product', ['limit' => 2]);

    expect($rows)->toHaveCount(2);
    expect($total)->toBe(5);
});

it('clamps an explicit ?limit= over the cap down to the cap', function () {
    config(['vifrost.max_limit' => 3]);

    [$rows, $total] = controllerIndexFor(Product::class, 'api/product', ['limit' => 999]);

    expect($rows)->toHaveCount(3);
    expect($total)->toBe(5);
});

it('shifts the result window with ?offset= without the total changing', function () {
    [$firstPage, $firstTotal] = controllerIndexFor(Product::class, 'api/product', ['limit' => 2, 'offset' => 0]);
    [$secondPage, $secondTotal] = controllerIndexFor(Product::class, 'api/product', ['limit' => 2, 'offset' => 2]);

    expect($firstPage->pluck('id')->all())->not->toEqual($secondPage->pluck('id')->all());
    expect($firstTotal)->toBe(5);
    expect($secondTotal)->toBe(5);
});

it('paginates a nested relation collection the same way as a flat index', function () {
    [$rows, $total] = controllerIndexFor(
        Product::class,
        'api/category/' . $this->category->id . '/products',
        ['limit' => 2]
    );

    expect($rows)->toHaveCount(2);
    expect($total)->toBe(5);
});

it('returns everything unbounded for a model configured as exempt, but still reports the real total', function () {
    config(['vifrost.max_limit_per_model' => [Product::class => null]]);

    [$rows, $total] = controllerIndexFor(Product::class, 'api/product');

    expect($rows)->toHaveCount(5);
    expect($total)->toBe(5);
});

it('rejects ?offset= with a 422 when given without ?limit= for a model exempt from pagination', function () {
    config(['vifrost.max_limit_per_model' => [Product::class => null]]);

    expect(fn() => controllerIndexFor(Product::class, 'api/product', ['offset' => 2]))
        ->toThrow(function (HttpException $e) {
            expect($e->getStatusCode())->toBe(422);
        });
});

it('still honors ?offset= for an exempt model when ?limit= is given explicitly alongside it', function () {
    config(['vifrost.max_limit_per_model' => [Product::class => null]]);

    [$rows, $total] = controllerIndexFor(Product::class, 'api/product', ['limit' => 2, 'offset' => 2]);

    expect($rows)->toHaveCount(2);
    expect($total)->toBe(5);
});

it('getCountForPagination() reports the true total even when called after limit()/offset() were applied', function () {
    app()->instance('request', Request::create('/api/product', 'GET', ['limit' => 2, 'offset' => 2]));

    $queryBuilder = QueryBuilder::for((new Vifrost)->getQuery())
        ->allowedFilters([])->allowedIncludes([])->allowedSorts(['id'])
        ->limit(2)->offset(2);

    expect($queryBuilder->toBase()->getCountForPagination())->toBe(5);
});

it('unlike getCountForPagination(), a plain ->count() silently returns 0 once ?offset= is beyond 0', function () {
    app()->instance('request', Request::create('/api/product', 'GET', ['limit' => 2, 'offset' => 2]));

    $queryBuilder = QueryBuilder::for((new Vifrost)->getQuery())
        ->allowedFilters([])->allowedIncludes([])->allowedSorts(['id'])
        ->limit(2)->offset(2);

    expect($queryBuilder->count())->toBe(0);
});
