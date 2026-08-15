<?php

use App\Models\Currency;
use Illuminate\Support\Facades\Artisan;

it('uses SYP as the base currency and keeps USD and SAR available in the initial data', function () {
    // SQLite in-memory testing opens a transaction around each feature test,
    // and a fresh migration run tries to VACUUM the database from within it.
    // Re-run the schema and seeders without a full migrate:fresh to keep the
    // test valid while preserving the real initial-data contract.
    Artisan::call('migrate', ['--force' => true]);
    Artisan::call('db:seed', ['--force' => true]);

    $base = Currency::query()->where('is_base', true)->first();

    expect($base)->not->toBeNull()
        ->and($base->code)->toBe('SYP');

    $codes = Currency::query()->pluck('code')->all();

    expect($codes)->toContain('SYP')
        ->toContain('USD')
        ->toContain('SAR');

    expect(Currency::query()->whereIn('code', ['USD', 'SAR'])->count())->toBe(2);
});
