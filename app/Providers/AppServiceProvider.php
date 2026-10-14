<?php

namespace App\Providers;

use App\Models\User;
use App\Support\SearchTerms;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
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

        // Permission names answer Gate checks ($user->can('products.edit'),
        // @can, authorize()) through User::hasPermission, so super admins pass
        // and inactive roles or permissions grant nothing. Spatie's own Gate
        // hook is off (config/permission.php) because it knows neither rule.
        // Any other ability falls through to its policy untouched — even for
        // super admins, so a policy can still refuse them.
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            $isPermission = app(PermissionRegistrar::class)
                ->getPermissions(['name' => $ability])
                ->isNotEmpty();

            return $isPermission && $user->hasPermission($ability) ? true : null;
        });
    }
}
