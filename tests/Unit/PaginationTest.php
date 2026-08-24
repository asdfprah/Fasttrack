<?php

use Vifrost\Laravel\Pagination;
use Illuminate\Http\Request;

class PaginationTestCategory
{
}

class PaginationTestProduct
{
}

function bindRequestWithQuery(array $query): void
{
    app()->instance('request', Request::create('/api/product', 'GET', $query));
}

beforeEach(function () {
    config(['vifrost.max_limit' => 100]);
    config(['vifrost.max_limit_per_model' => []]);
    bindRequestWithQuery([]);
});

describe('Pagination::maxLimitFor', function () {
    it('falls back to the global max_limit when the model has no override', function () {
        expect(Pagination::maxLimitFor(PaginationTestProduct::class))->toBe(100);
    });

    it('uses a per-model override when one exists', function () {
        config(['vifrost.max_limit_per_model' => [PaginationTestProduct::class => 20]]);

        expect(Pagination::maxLimitFor(PaginationTestProduct::class))->toBe(20);
    });

    it('treats a null per-model override as "exempt from pagination"', function () {
        config(['vifrost.max_limit_per_model' => [PaginationTestCategory::class => null]]);

        expect(Pagination::maxLimitFor(PaginationTestCategory::class))->toBeNull();
    });

    it('normalizes a leading backslash so it matches a ::class-style config key', function () {
        config(['vifrost.max_limit_per_model' => [PaginationTestProduct::class => 20]]);

        expect(Pagination::maxLimitFor('\\'.PaginationTestProduct::class))->toBe(20);
    });
});

describe('Pagination::resolveLimit', function () {
    it('defaults to the max_limit when no ?limit= is given', function () {
        expect(Pagination::resolveLimit(PaginationTestProduct::class))->toBe(100);
    });

    it('honors a ?limit= under the cap', function () {
        bindRequestWithQuery(['limit' => '10']);

        expect(Pagination::resolveLimit(PaginationTestProduct::class))->toBe(10);
    });

    it('clamps a ?limit= over the cap down to the cap', function () {
        bindRequestWithQuery(['limit' => '99999']);

        expect(Pagination::resolveLimit(PaginationTestProduct::class))->toBe(100);
    });

    it('clamps a non-positive ?limit= up to 1 instead of passing it straight to the DB', function () {
        bindRequestWithQuery(['limit' => '-5']);

        expect(Pagination::resolveLimit(PaginationTestProduct::class))->toBe(1);
    });

    it('returns null (no limit applied) for an exempt model with no ?limit= given', function () {
        config(['vifrost.max_limit_per_model' => [PaginationTestCategory::class => null]]);

        expect(Pagination::resolveLimit(PaginationTestCategory::class))->toBeNull();
    });

    it('still honors an explicit ?limit= for an otherwise-exempt model', function () {
        config(['vifrost.max_limit_per_model' => [PaginationTestCategory::class => null]]);
        bindRequestWithQuery(['limit' => '5']);

        expect(Pagination::resolveLimit(PaginationTestCategory::class))->toBe(5);
    });
});

describe('Pagination::resolveOffset', function () {
    it('defaults to 0 with no ?offset= given', function () {
        expect(Pagination::resolveOffset())->toBe(0);
    });

    it('honors a positive ?offset=', function () {
        bindRequestWithQuery(['offset' => '20']);

        expect(Pagination::resolveOffset())->toBe(20);
    });

    it('clamps a negative ?offset= up to 0', function () {
        bindRequestWithQuery(['offset' => '-20']);

        expect(Pagination::resolveOffset())->toBe(0);
    });
});
