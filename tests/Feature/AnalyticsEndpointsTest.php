<?php

use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\PeriodComparison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The analytics module answered its screens with hardcoded numbers for so long
 * that nobody noticed `sales/top-products` had never once returned a response:
 * it selected a `products.name` column that does not exist and threw a 500.
 *
 * These walk every endpoint the screens call. A broken query shows up as a red
 * test rather than as a panel that quietly stays empty.
 */

/** Every read endpoint, with the query string the frontend actually sends. */
dataset('analytics endpoints', [
    'overview' => ['overview'],
    'sales summary' => ['sales/summary'],
    'sales trend' => ['sales/trend?days=30&group_by=day'],
    'sales by channel' => ['sales/by-channel'],
    'sales top products' => ['sales/top-products?limit=10'],
    'sales customer analytics' => ['sales/customer-analytics'],
    'sales forecast' => ['sales/forecast?days=30&forecast_days=7'],
    'sales conversion funnel' => ['sales/conversion-funnel'],
    'inventory summary' => ['inventory/summary'],
    'inventory turnover' => ['inventory/turnover'],
    'inventory slow moving' => ['inventory/slow-moving'],
    'inventory stockout' => ['inventory/stockout'],
    'inventory valuation' => ['inventory/valuation'],
    'inventory abc' => ['inventory/abc'],
    'inventory health score' => ['inventory/health-score'],
    'warehouse performance' => ['warehouse/performance'],
    'warehouse bin utilization' => ['warehouse/bin-utilization'],
    'warehouse cycle count accuracy' => ['warehouse/cycle-count-accuracy'],
    'warehouse picker performance' => ['warehouse/picker-performance'],
    'warehouse capacity planning' => ['warehouse/capacity-planning'],
    'financial summary' => ['financial/summary'],
    'financial revenue by category' => ['financial/revenue-by-category'],
    'financial expenses' => ['financial/expenses'],
    'financial cash flow' => ['financial/cash-flow'],
    'financial profit and loss' => ['financial/profit-loss'],
    'financial aging' => ['financial/aging'],
    'financial ratios' => ['financial/ratios'],
    'financial budget vs actual' => ['financial/budget-vs-actual'],
    'metrics' => ['metrics'],
    'reports' => ['reports'],
    'dashboards' => ['dashboards'],
    'visitors summary' => ['visitors/summary'],
    'visitors trend' => ['visitors/trend'],
    'visitors breakdown' => ['visitors/breakdown'],
    'visitors top pages' => ['visitors/top-pages?limit=10'],
    'visitors log' => ['visitors/log'],
    'visitors filters' => ['visitors/filters'],
]);

it('answers every analytics endpoint', function (string $endpoint) {
    // An admin, because the financial endpoints sit behind the accountant role
    // — profit, margins and receivables ageing are the same picture the books
    // give, so they answer to the same audience.
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->getJson("/api/v1/analytics/{$endpoint}")
        ->assertOk();
})->with('analytics endpoints');

it('reports top selling products without touching a column that does not exist', function () {
    $user = User::factory()->create();

    // The regression itself: `products` has name_ar/name_en and no `name`,
    // and the line total on a sales order item is `total`, not `total_price`.
    expect(Schema::hasColumn('products', 'name'))->toBeFalse()
        ->and(Schema::hasColumn('sales_order_items', 'total_price'))->toBeFalse()
        ->and(Schema::hasColumn('sales_order_items', 'total'))->toBeTrue();

    $this->actingAs($user)
        ->getJson('/api/v1/analytics/sales/top-products')
        ->assertOk()
        ->assertJsonIsArray();
});

/**
 * Places one delivered order so the revenue figures have something to find.
 *
 * @return array{product_id: int, revenue: float}
 */
function seedDeliveredOrder(): array
{
    $product = Product::factory()->create(['name_ar' => 'خلاط مغسلة', 'name_en' => 'Basin Mixer']);

    $orderId = DB::table('sales_orders')->insertGetId([
        'order_number' => 'SO-TEST-1',
        'status' => SalesOrder::STATUS_DELIVERED,
        'order_date' => now()->subDays(3)->toDateString(),
        'subtotal' => 1500,
        'total' => 1500,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sales_order_items')->insert([
        'sales_order_id' => $orderId,
        'product_id' => $product->id,
        'quantity' => 3,
        'unit_price' => 500,
        'total' => 1500,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['product_id' => $product->id, 'revenue' => 1500.0];
}

/**
 * The dangerous shape of these bugs is the silent zero, not the crash.
 *
 * Summing the absent `total_price` threw no error — it returned 0, so revenue
 * reports rendered perfectly while claiming every product and every category
 * earned nothing. An `assertOk` would have passed the whole time; only asserting
 * on a known figure catches it.
 */
it('counts real revenue in top selling products rather than reporting zero', function () {
    $seeded = seedDeliveredOrder();
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson('/api/v1/analytics/sales/top-products')
        ->assertOk();

    // Loose comparison: JSON gives back 1500 as an int when it has no fraction,
    // and the point of the assertion is the amount, not its PHP type.
    expect($response->json())->toHaveCount(1)
        ->and($response->json('0.revenue'))->toEqual($seeded['revenue'])
        ->and($response->json('0.quantity'))->toEqual(3)
        ->and($response->json('0.product_name'))->not->toBe('Unknown');
});

it('counts real revenue by category rather than reporting zero', function () {
    seedDeliveredOrder();
    // Financial analytics are behind the accountant role.
    $user = User::factory()->admin()->create();

    $rows = $this->actingAs($user)
        ->getJson('/api/v1/analytics/financial/revenue-by-category')
        ->assertOk()
        ->json();

    expect(collect($rows)->sum('revenue'))->toEqual(1500);
});

it('gives the overview card row a real comparison window', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson('/api/v1/analytics/overview?from_date=2026-03-01&to_date=2026-03-31')
        ->assertOk();

    // March is 31 days, so the window before it ends on the last day of February.
    $response->assertJsonPath('period.previous_from', '2026-01-29')
        ->assertJsonPath('period.previous_to', '2026-02-28');

    foreach (['revenue', 'orders', 'gross_margin'] as $metric) {
        $response->assertJsonStructure([
            $metric => ['current', 'previous', 'change', 'change_percent', 'direction'],
        ]);
    }
});

it('gives the visitors summary a real comparison window', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson('/api/v1/analytics/visitors/summary?from_date=2026-03-01&to_date=2026-03-31')
        ->assertOk();

    $response->assertJsonPath('period.previous_from', '2026-01-29')
        ->assertJsonPath('period.previous_to', '2026-02-28');

    foreach (['total_visits', 'unique_visitors', 'bot_share'] as $metric) {
        $response->assertJsonStructure([
            $metric => ['current', 'previous', 'change', 'change_percent', 'direction'],
        ]);
    }
});

it('keeps API and admin polling out of the visitor traffic it reports', function () {
    DB::table('visitors')->insert([
        [
            // A real storefront visit — the only row that should be counted.
            'ip_address' => '10.0.0.1',
            'page_url' => 'https://example.test/',
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'is_bot' => false,
            'visited_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            // The admin SPA polling an API route — TrackVisitors records this
            // because axios never sets the header its ajax() check looks for.
            'ip_address' => '10.0.0.2',
            'page_url' => 'https://example.test/api/v1/notifications/unread-count',
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'is_bot' => false,
            'visited_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            // The bare /admin route — TrackVisitors only skips `admin/*`.
            'ip_address' => '10.0.0.3',
            'page_url' => 'https://example.test/admin',
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'is_bot' => false,
            'visited_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $user = User::factory()->create();
    $from = now()->subDay()->toDateString();
    $to = now()->addDay()->toDateString();

    // Without this, the test's own requests to these `/api/...` routes would
    // be recorded by TrackVisitors and inflate the very rows being asserted on.
    $this->withoutMiddleware(\App\Http\Middleware\TrackVisitors::class);

    $summary = $this->actingAs($user)
        ->getJson("/api/v1/analytics/visitors/summary?from_date={$from}&to_date={$to}")
        ->assertOk()
        ->json();

    expect($summary['total_visits']['current'])->toEqual(1);

    $pages = $this->actingAs($user)
        ->getJson("/api/v1/analytics/visitors/top-pages?from_date={$from}&to_date={$to}")
        ->assertOk()
        ->json('pages');

    expect(collect($pages)->pluck('page_url'))
        ->toContain('https://example.test/')
        ->not->toContain('https://example.test/api/v1/notifications/unread-count')
        ->not->toContain('https://example.test/admin');

    // The raw log is the audit trail, not the report: all three rows stay visible.
    $logged = $this->actingAs($user)
        ->getJson("/api/v1/analytics/visitors/log?from_date={$from}&to_date={$to}")
        ->assertOk()
        ->json('total');

    expect($logged)->toBe(3);
});

it('exports a domain as CSV that Excel will read as UTF-8', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/api/v1/analytics/export/sales')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())->toStartWith("\xEF\xBB\xBF");
});

it('refuses an unknown export domain instead of returning an empty file', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/api/v1/analytics/export/nonsense')
        ->assertNotFound();
});

/* ------------------------------------------------------------------ *
 * The comparison layer that replaced the hardcoded trend percentages.
 * ------------------------------------------------------------------ */

it('compares against the equal-length window immediately before', function () {
    expect(PeriodComparison::previousWindow('2026-03-01', '2026-03-31'))
        ->toBe(['from' => '2026-01-29', 'to' => '2026-02-28']);

    // A single day compares against the day before it.
    expect(PeriodComparison::previousWindow('2026-03-10', '2026-03-10'))
        ->toBe(['from' => '2026-03-09', 'to' => '2026-03-09']);
});

it('survives a reversed range rather than returning nonsense', function () {
    expect(PeriodComparison::previousWindow('2026-03-31', '2026-03-01'))
        ->toBe(['from' => '2026-01-29', 'to' => '2026-02-28']);
});

it('reports no percentage when there is nothing to compare against', function () {
    $grown = PeriodComparison::compare(500, 0);

    // Going from nothing to something is not a percentage — and must not be a
    // division by zero either.
    expect($grown['change_percent'])->toBeNull()
        ->and($grown['direction'])->toBe('up')
        ->and($grown['change'])->toBe(500.0);
});

it('states a rise, a fall and a flat metric distinctly', function () {
    expect(PeriodComparison::compare(150, 100)['change_percent'])->toBe(50.0);
    expect(PeriodComparison::compare(150, 100)['direction'])->toBe('up');

    expect(PeriodComparison::compare(75, 100)['change_percent'])->toBe(-25.0);
    expect(PeriodComparison::compare(75, 100)['direction'])->toBe('down');

    expect(PeriodComparison::compare(100, 100)['direction'])->toBe('flat');
    expect(PeriodComparison::compare(100, 100)['change_percent'])->toBe(0.0);
});

it('treats a null or non-numeric metric as zero rather than crashing', function () {
    expect(PeriodComparison::compare(null, null))
        ->toMatchArray(['current' => 0.0, 'previous' => 0.0, 'direction' => 'flat', 'change_percent' => null]);

    expect(PeriodComparison::compare('1250.75', '1000')['change_percent'])->toBe(25.1);
});

it('does not present a floating point crumb as a rise', function () {
    expect(PeriodComparison::compare(0.001, 0.0)['direction'])->toBe('flat');
});
