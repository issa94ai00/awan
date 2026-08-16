<?php

use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The export buttons used to pop "export started" and send no request at all.
 * These check that a real file comes back, with real rows in it, encoded so
 * that Excel on Windows reads Arabic product names rather than mojibake.
 */

beforeEach(function () {
    $this->user = User::factory()->create();

    $product = Product::factory()->create([
        'name_ar' => 'خلاط مغسلة شك قصير',
        'name_en' => 'Short Basin Mixer',
    ]);

    $orderId = DB::table('sales_orders')->insertGetId([
        'order_number' => 'SO-EXPORT-1',
        'status' => SalesOrder::STATUS_DELIVERED,
        'order_date' => now()->subDays(2)->toDateString(),
        'subtotal' => 2400,
        'total' => 2400,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sales_order_items')->insert([
        'sales_order_id' => $orderId,
        'product_id' => $product->id,
        'quantity' => 4,
        'unit_price' => 600,
        'total' => 2400,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('exports rows, not just headers', function () {
    $csv = $this->actingAs($this->user)
        ->get('/api/v1/analytics/export/sales')
        ->assertOk()
        ->streamedContent();

    $body = ltrim($csv, "\xEF\xBB\xBF");
    $lines = array_values(array_filter(explode("\n", trim($body))));

    // Header plus the one product that sold.
    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('product_name')
        ->and($lines[1])->toContain('2400');
});

it('writes Arabic that survives the round trip', function () {
    $csv = $this->actingAs($this->user)
        ->get('/api/v1/analytics/export/sales')
        ->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('خلاط مغسلة شك قصير');

    // Valid UTF-8 throughout, which is what the BOM is promising the reader.
    expect(mb_check_encoding(ltrim($csv, "\xEF\xBB\xBF"), 'UTF-8'))->toBeTrue();
});

it('names the file after the domain and the period it covers', function () {
    $this->actingAs($this->user)
        ->get('/api/v1/analytics/export/sales?from_date=2026-01-01&to_date=2026-01-31')
        ->assertOk()
        ->assertDownload('analytics-sales-2026-01-01-to-2026-01-31.csv');
});

it('still returns a usable file when the period has no rows', function () {
    $csv = $this->actingAs($this->user)
        ->get('/api/v1/analytics/export/sales?from_date=2020-01-01&to_date=2020-01-31')
        ->assertOk()
        ->streamedContent();

    // A BOM and nothing else: an empty sheet, not a broken download.
    expect($csv)->toBe("\xEF\xBB\xBF");
});

it('exports every domain the toolbar offers', function (string $domain) {
    $this->actingAs($this->user)
        ->get("/api/v1/analytics/export/{$domain}")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
})->with(['sales', 'inventory', 'warehouse', 'financial']);
