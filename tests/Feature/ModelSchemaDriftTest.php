<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Every mass-assignable attribute must be somewhere real.
 *
 * This is the guard for a class of bug that has bitten this system repeatedly
 * and always the same way: a model declares a field the table never got.
 *
 *   - `warehouse_bins` was created with six columns while the model and the
 *     WMS controller were written against nineteen. Production answered every
 *     bin request with `Unknown column 'is_active' in 'where clause'`.
 *   - `users` never got `phone`, which `AuthController` wrote on every
 *     registration and read back on every phone sign-in. Registration returned
 *     500 to every caller.
 *   - `products.status` and `products.name` were listed as fillable long after
 *     the columns were renamed or replaced by accessors, so those keys were
 *     dropped from every mass assignment without a word.
 *
 * The reason none of it surfaced in this suite is worth stating plainly: these
 * tests run on SQLite, which reads an unresolvable double-quoted identifier as
 * a *string literal* rather than failing. `where "is_active" = 1` quietly
 * becomes `where 'is_active' = 1`, matches nothing and returns 200. MySQL
 * raises error 1054 instead. So a behavioural test cannot be trusted to catch
 * this — asserting the columns exist can, and does so on either engine.
 */

/**
 * Attributes that legitimately have no column of their own.
 *
 * A field is fine in `$fillable` without a column when the model defines a
 * mutator for it: `Employee::setNameAttribute()` splits a full name across
 * `first_name` and `last_name`, so `name` is mass-assignable and real, just not
 * stored under that name.
 */
function hasMutator(object $model, string $attribute): bool
{
    return method_exists($model, 'set' . Str::studly($attribute) . 'Attribute');
}

/**
 * Every model class in the app.
 *
 * Resolved from the directory rather than through `app_path()`: dataset
 * closures are evaluated while the test suite is being collected, before the
 * application container exists.
 */
dataset('models', function () {
    $models = glob(__DIR__ . '/../../app/Models/*.php') ?: [];

    foreach ($models as $file) {
        $name = basename($file, '.php');
        yield $name => ['App\\Models\\' . $name];
    }
});

it('declares no mass-assignable field the table cannot hold', function (string $class) {
    // Abstract bases, enums and anything not instantiable as a model are not
    // this test's subject.
    if (! class_exists($class) || ! is_subclass_of($class, Illuminate\Database\Eloquent\Model::class)) {
        expect(true)->toBeTrue();

        return;
    }

    $model = new $class;
    $table = $model->getTable();

    if (! Schema::hasTable($table)) {
        // A model whose table is not in this schema has nothing to check here.
        expect(true)->toBeTrue();

        return;
    }

    $columns = Schema::getColumnListing($table);

    $phantom = array_values(array_filter(
        $model->getFillable(),
        fn (string $attribute) => ! in_array($attribute, $columns, true) && ! hasMutator($model, $attribute)
    ));

    // The failure message carries the remedy, because the fix is never obvious
    // from the field name alone: add the column, add a mutator, or drop it.
    $this->assertSame([], $phantom, sprintf(
        '%s lists [%s] as fillable, but table "%s" has no such column and the model has no mutator for it. '
        .'Either add the column, add a mutator, or drop it from $fillable.',
        class_basename($class),
        implode(', ', $phantom),
        $table
    ));
})->with('models');

/**
 * The specific columns whose absence has already caused an outage. Named
 * individually so a regression points straight at the incident rather than at
 * a generic drift failure.
 */
it('keeps the columns whose absence broke production', function () {
    expect(Schema::hasColumn('users', 'phone'))->toBeTrue()
        ->and(Schema::hasColumn('warehouse_bins', 'is_active'))->toBeTrue()
        ->and(Schema::hasColumn('warehouse_bins', 'capacity_value'))->toBeTrue()
        ->and(Schema::hasColumn('sales_order_items', 'total'))->toBeTrue();
});

it('recognises a mutator-backed attribute as legitimate', function () {
    $employee = new App\Models\Employee;

    // Guards the guard: `name` is fillable on Employee and has no column, which
    // is correct because a mutator writes first_name and last_name from it. If
    // this stopped being recognised, the drift test above would start failing
    // on working code.
    expect(Schema::hasColumn('employees', 'name'))->toBeFalse()
        ->and(in_array('name', $employee->getFillable(), true))->toBeTrue()
        ->and(hasMutator($employee, 'name'))->toBeTrue();
});
