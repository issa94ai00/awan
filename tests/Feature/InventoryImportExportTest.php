<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\ProductExcelService;
use Illuminate\Http\UploadedFile;

/**
 * The stock sheet round trip.
 *
 * A sheet is a count document: it states what is physically on the shelf and in
 * what condition. Everything downstream — reservation, the field app's sourcing
 * and restock screens — reads `available_quantity - reserved_quantity`, so an
 * import that does not keep the buckets adding up quietly corrupts all of them.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->token = $this->admin->createToken('test-token')->plainTextToken;
    $this->excel = app(ProductExcelService::class);

    $this->warehouse = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'location' => 'الرياض',
        'status' => 'active',
        'is_active' => true,
        'is_primary' => true,
        'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->product = Product::create([
        'name_ar' => 'خلاط مغسلة',
        'sku' => 'SKU-MIXER',
        'price' => 100,
        'cost_price' => 60,
    ]);

    $this->row = function (array $overrides = []) {
        return WarehouseInventory::create(array_merge([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'available_quantity' => 10,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
            'quarantined_quantity' => 0,
            'reorder_point' => 3,
        ], $overrides));
    };

    // Builds a one-row sheet with exactly the given headers, then imports it.
    // Headers are declared per test rather than fixed, because "which columns
    // the sheet happens to carry" is the thing most of these tests are about.
    $this->importSheet = function (array $sheetRow) {
        $columns = [];
        foreach ($sheetRow as $header => $value) {
            $columns[$header] = is_numeric($value) ? 'number' : 'string';
        }

        $path = tempnam(sys_get_temp_dir(), 'stock') . '.xlsx';
        file_put_contents($path, $this->excel->buildXlsx($columns, [$sheetRow]));

        $result = $this->excel->importStockFile(
            new UploadedFile($path, 'stock.xlsx', null, null, true)
        );

        @unlink($path);

        return $result;
    };
});

test('the exported sellable figure matches what the inventory panel shows', function () {
    // 10 on hand, 3 promised to orders, 2 broken: 5 can actually be sold.
    ($this->row)([
        'quantity' => 10,
        'available_quantity' => 8,
        'reserved_quantity' => 3,
        'damaged_quantity' => 2,
    ]);

    $binary = $this->excel->exportWarehouseInventory(
        WarehouseInventory::with(['product', 'warehouse'])->get()
    );

    $path = tempnam(sys_get_temp_dir(), 'export') . '.xlsx';
    file_put_contents($path, $binary);
    $rows = $this->excel->read($path);
    @unlink($path);

    $sheet = $rows[0];

    expect((int) $sheet['الكمية الإجمالية'])->toBe(10);
    expect((int) $sheet['التالفة'])->toBe(2);
    expect((int) $sheet['المحجوز (محسوب)'])->toBe(3);
    expect((int) $sheet['السليمة (محسوب)'])->toBe(8);

    // The number the panel prints: quantity - reserved - damaged - quarantined.
    // This is the column that used to carry the good-condition bucket under the
    // same name, reading 8 where the screen said 5.
    expect((int) $sheet['المتاح للبيع (محسوب)'])->toBe(5);
    expect((int) $sheet['سعر البيع'])->toBe(100);
    expect((int) $sheet['سعر التكلفة'])->toBe(60);
});

test('a counted damage figure moves stock out of the sellable bucket', function () {
    ($this->row)();

    ($this->importSheet)([
        'المستودع' => 'المستودع الرئيسي',
        'SKU' => 'SKU-MIXER',
        'الاسم' => 'خلاط مغسلة',
        'الكمية الإجمالية' => 10,
        'التالفة' => 4,
    ]);

    $row = WarehouseInventory::first();

    // Still ten units on the shelf — four of them unsellable.
    expect((int) $row->quantity)->toBe(10);
    expect((int) $row->damaged_quantity)->toBe(4);
    expect((int) $row->available_quantity)->toBe(6);

    // The invariant everything downstream depends on.
    expect((int) $row->quantity)->toBe(
        (int) $row->available_quantity + (int) $row->damaged_quantity + (int) $row->quarantined_quantity
    );
});

test('a column the sheet omits leaves that bucket alone', function () {
    ($this->row)(['quantity' => 10, 'available_quantity' => 7, 'damaged_quantity' => 3]);

    ($this->importSheet)([
        'المستودع' => 'المستودع الرئيسي',
        'SKU' => 'SKU-MIXER',
        'الكمية الإجمالية' => 12,
    ]);

    $row = WarehouseInventory::first();

    // The damage was not re-counted, so it stands; the extra units are good.
    expect((int) $row->damaged_quantity)->toBe(3);
    expect((int) $row->available_quantity)->toBe(9);
    expect((int) $row->quantity)->toBe(12);
});

test('an omitted reorder point does not reset the existing trigger', function () {
    ($this->row)(['reorder_point' => 25]);

    ($this->importSheet)([
        'المستودع' => 'المستودع الرئيسي',
        'SKU' => 'SKU-MIXER',
        'الكمية الإجمالية' => 10,
    ]);

    // Zeroing this would silence the restock screen for the product entirely.
    expect((int) WarehouseInventory::first()->reorder_point)->toBe(25);
});

test('the reserved column is reported as ignored rather than applied', function () {
    ($this->row)(['reserved_quantity' => 4]);

    $result = ($this->importSheet)([
        'المستودع' => 'المستودع الرئيسي',
        'SKU' => 'SKU-MIXER',
        'الكمية الإجمالية' => 10,
        'المحجوز (محسوب)' => 0,
        'المتاح للبيع (محسوب)' => 10,
    ]);

    // A sheet cannot release a hold placed against a real customer order.
    expect((int) WarehouseInventory::first()->reserved_quantity)->toBe(4);
    expect($result['ignored_columns'])->toContain('المحجوز');
    expect($result['ignored_columns'])->toContain('المتاح للبيع');
});

test('a count that contradicts itself is refused with the row named', function () {
    ($this->row)();

    $result = ($this->importSheet)([
        'المستودع' => 'المستودع الرئيسي',
        'SKU' => 'SKU-MIXER',
        'الكمية الإجمالية' => 5,
        'التالفة' => 4,
        'المحتجزة' => 3,
    ]);

    expect($result['errors'])->toHaveCount(1);
    // Row 2, because row 1 is the header — which is what the operator sees.
    expect($result['errors'][0]['row'])->toBe(2);
    expect($result['errors'][0]['message'])->toContain('تتجاوزان الكمية الإجمالية');

    // Refused means untouched.
    expect((int) WarehouseInventory::first()->quantity)->toBe(10);
});

test('a count below what is already promised to orders is refused', function () {
    ($this->row)(['quantity' => 10, 'available_quantity' => 10, 'reserved_quantity' => 8]);

    $result = ($this->importSheet)([
        'المستودع' => 'المستودع الرئيسي',
        'SKU' => 'SKU-MIXER',
        'الكمية الإجمالية' => 3,
    ]);

    // Accepting this would leave the warehouse holding 8 units for orders out
    // of 3 on the shelf, and the shortfall would surface at picking time.
    expect($result['errors'])->toHaveCount(1);
    expect($result['errors'][0]['message'])->toContain('أقل من المحجوز');
    expect((int) WarehouseInventory::first()->quantity)->toBe(10);
});

test('prices on the sheet reach products that already exist', function () {
    ($this->row)();

    $result = ($this->importSheet)([
        'المستودع' => 'المستودع الرئيسي',
        'SKU' => 'SKU-MIXER',
        'سعر البيع' => 150,
        'سعر التكلفة' => 90,
        'الكمية الإجمالية' => 10,
    ]);

    expect($result['prices_updated'])->toBe(1);
    expect((float) $this->product->fresh()->price)->toBe(150.0);
    expect((float) $this->product->fresh()->cost_price)->toBe(90.0);
});

test('a sheet exported before the columns were renamed still imports', function () {
    ($this->row)();

    // The old header, still understood.
    ($this->importSheet)([
        'المستودع' => 'المستودع الرئيسي',
        'SKU' => 'SKU-MIXER',
        'السعر' => 120,
        'الكمية' => 7,
        'نقطة إعادة الترتيب' => 5,
    ]);

    $row = WarehouseInventory::first();
    expect((int) $row->quantity)->toBe(7);
    expect((int) $row->available_quantity)->toBe(7);
    expect((int) $row->reorder_point)->toBe(5);
    expect((float) $this->product->fresh()->price)->toBe(120.0);
});

test('the counted change is written to the movement log', function () {
    ($this->row)();

    ($this->importSheet)([
        'المستودع' => 'المستودع الرئيسي',
        'SKU' => 'SKU-MIXER',
        'الكمية الإجمالية' => 10,
        'التالفة' => 4,
    ]);

    // Four units left the good bucket and four entered damaged: two real events,
    // each with its own record, rather than one net zero that explains nothing.
    $movements = \App\Models\StockMovement::where('source', 'stock_import')->get();
    expect($movements)->toHaveCount(2);
    expect($movements->pluck('movement_type')->unique()->all())->toBe(['adjustment']);
});

test('the endpoint round trips through HTTP', function () {
    ($this->row)(['quantity' => 10, 'available_quantity' => 10, 'reserved_quantity' => 2]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->get('/api/v1/admin/inventory/export');

    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))
        ->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = tempnam(sys_get_temp_dir(), 'roundtrip') . '.xlsx';
    file_put_contents($path, $response->getContent());

    $result = $this->excel->importStockFile(
        new UploadedFile($path, 'stock.xlsx', null, null, true)
    );
    @unlink($path);

    // Re-importing an untouched export must change nothing and fail nothing.
    expect($result['errors'])->toBe([]);
    $row = WarehouseInventory::first();
    expect((int) $row->quantity)->toBe(10);
    expect((int) $row->reserved_quantity)->toBe(2);
    expect((int) $row->available_quantity)->toBe(10);
});
