<?php

namespace App\Providers;

use App\Support\SearchTerms;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ->whereSearch(['name', 'customer.name'], $request->search): every
        // word of the search has to appear somewhere, in any order.
        $whereSearch = function (array $columns, ?string $term) {
            return SearchTerms::apply($this, $columns, $term);
        };
        QueryBuilder::macro('whereSearch', $whereSearch);
        EloquentBuilder::macro('whereSearch', $whereSearch);
    }
}
