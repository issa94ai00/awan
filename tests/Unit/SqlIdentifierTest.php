<?php

use App\Models\WarehouseInventory;
use App\Support\SqlIdentifier;

it('accepts plain and qualified identifiers', function () {
    expect(SqlIdentifier::assert('warehouse_inventory'))->toBe('warehouse_inventory')
        ->and(SqlIdentifier::assert('wi.available_quantity'))->toBe('wi.available_quantity');
});

it('refuses anything that could carry SQL', function (string $bad) {
    SqlIdentifier::assert($bad);
})->with([
    'semicolon' => ['t; DROP TABLE users'],
    'quote' => ["t'"],
    'comment' => ['t--'],
    'space' => ['a b'],
    'empty' => [''],
    'leading digit' => ['1t'],
])->throws(InvalidArgumentException::class);

it('guards the availability expression builder', function () {
    expect(WarehouseInventory::availableSql('wi'))->toContain('wi.available_quantity');
    WarehouseInventory::availableSql('wi; DROP TABLE x');
})->throws(InvalidArgumentException::class);
