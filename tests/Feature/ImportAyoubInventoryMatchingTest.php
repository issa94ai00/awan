<?php

use App\Console\Commands\ImportAyoubInventory;
use App\Models\Product;
use App\Models\Category;

/** How the opening-stock import pairs a spreadsheet row with a catalogue product. */
function ayoubCall(string $method, ...$args)
{
    $command = app(ImportAyoubInventory::class);
    $ref = new ReflectionMethod($command, $method);
    $ref->setAccessible(true);

    return $ref->invoke($command, ...$args);
}

beforeEach(function () {
    $this->category = Category::create(['name_ar' => 'تصنيف', 'name_en' => 'Cat', 'slug' => 'cat-'.uniqid()]);
    $this->make = fn (string $name, ?string $sku = null, float $cost = 1.0) => Product::create([
        'category_id' => $this->category->id,
        'name_ar' => $name,
        'slug' => 'p-'.uniqid(),
        'sku' => $sku ?? 'SKU-'.uniqid(),
        'price' => 10,
        'cost_price' => $cost,
        'unit' => 'قطعة',
    ]);
});

it('matches a row by exact product name', function () {
    $p = ($this->make)('خلاط مطبخ نحاس');
    ($this->make)('مفتاح كهرباء');

    $found = ayoubCall('resolveProduct', ['row' => 999, 'desc' => 'خلاط مطبخ نحاس', 'size' => null, 'price' => 1.0], ayoubCall('buildMatchIndex'));

    expect($found?->id)->toBe($p->id);
});

it('matches by shared words when cost agrees, and returns null when nothing is close', function () {
    $p = ($this->make)('خلاط مطبخ نحاس ثقيل', null, 2.5);

    $near = ayoubCall('resolveProduct', ['row' => 998, 'desc' => 'خلاط مطبخ نحاس', 'size' => null, 'price' => 2.5], ayoubCall('buildMatchIndex'));
    $none = ayoubCall('resolveProduct', ['row' => 997, 'desc' => 'سخان كهربائي', 'size' => null, 'price' => 99.0], ayoubCall('buildMatchIndex'));

    expect($near?->id)->toBe($p->id)->and($none)->toBeNull();
});

it('plans a match or a create for every row', function () {
    $p = ($this->make)('خلاط مطبخ نحاس');

    [$plan, $matched, $created] = ayoubCall('planImport', [
        ['row' => 996, 'desc' => 'خلاط مطبخ نحاس', 'size' => null, 'price' => 1.0, 'qty' => 3.0],
        ['row' => 995, 'desc' => 'صنف غير موجود تماما', 'size' => null, 'price' => 5.0, 'qty' => 1.0],
    ], ayoubCall('buildMatchIndex'));

    expect($matched)->toBe(1)->and($created)->toBe(1)
        ->and($plan[0]['action'])->toBe('match')->and($plan[1]['action'])->toBe('create');
});
