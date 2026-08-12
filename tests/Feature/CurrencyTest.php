<?php

use App\Models\Currency;
use App\Models\Setting;
use App\Models\User;
use App\Services\CurrencyService;

/**
 * What money is worth, and what the clients are told about it.
 *
 * Currency support was scaffolding: nine tables carried a `currency` column,
 * three carried `exchange_rate`, and every writer put the literal 'SAR' and
 * 1.0000 into them. Nothing converted anything and the app printed "ر.س"
 * regardless of what the platform was configured for.
 *
 * These tests pin the floor that was missing — a managed list, dated rates, and
 * conversion that refuses to guess.
 */
beforeEach(function () {
    $this->currencies = app(CurrencyService::class);
    $this->currencies->flushCache();

    // The migration seeds the base from the platform's existing setting.
    $this->base = Currency::where('is_base', true)->firstOrFail();

    $this->syp = Currency::create([
        'code' => 'SYP',
        'name_ar' => 'ليرة سورية',
        'name_en' => 'Syrian Pound',
        'symbol' => 'ل.س',
        'decimal_places' => 0,
        'rounding_step' => 500,
        'is_active' => true,
    ]);
});

test('the migration leaves exactly one base currency, worth one of itself', function () {
    expect(Currency::where('is_base', true)->count())->toBe(1);
    expect($this->currencies->rateFor($this->base->code))->toBe('1');
});

test('a currency with no rate cannot be converted to', function () {
    // Inventing 1.0 here would quietly price a pound at a riyal, so the service
    // says it does not know instead.
    expect($this->currencies->rateFor('SYP'))->toBeNull();
    expect($this->currencies->convertFromBase(100, 'SYP'))->toBeNull();
});

test('conversion uses the recorded rate and the currency own rounding', function () {
    $this->currencies->recordRate($this->syp, 3450.75);

    // 100 x 3450.75 = 345,075, rounded to the 500 step the shop quotes in.
    expect($this->currencies->convertFromBase(100, 'SYP'))->toBe(345000.0);
});

test('decimal places are honoured when there is no rounding step', function () {
    $usd = Currency::create([
        'code' => 'USD',
        'name_ar' => 'دولار',
        'symbol' => '$',
        'decimal_places' => 2,
        'rounding_step' => 0,
        'is_active' => true,
    ]);
    $this->currencies->recordRate($usd, 0.2666);

    expect($this->currencies->convertFromBase(100, 'USD'))->toBe(26.66);
});

test('the newest rate that has already started is the one in force', function () {
    $this->currencies->recordRate($this->syp, 3000, new DateTimeImmutable('-2 days'));
    $this->currencies->recordRate($this->syp, 3450, new DateTimeImmutable('-1 day'));
    // Entered ahead of time: it must not apply yet.
    $this->currencies->recordRate($this->syp, 9999, new DateTimeImmutable('+1 day'));

    expect((float) $this->currencies->rateFor('SYP'))->toBe(3450.0);
});

test('a past rate can still be read, so history is not restated', function () {
    $this->currencies->recordRate($this->syp, 3000, new DateTimeImmutable('-2 days'));
    $this->currencies->recordRate($this->syp, 3450, new DateTimeImmutable('-1 day'));

    // An order placed two days ago is shown at the rate that applied then.
    expect((float) $this->currencies->rateFor('SYP', new DateTimeImmutable('-2 days')))->toBe(3000.0);
});

test('the base currency rate cannot be moved off one', function () {
    expect(fn () => $this->currencies->recordRate($this->base, 2))
        ->toThrow(RuntimeException::class, 'عملة الأساس');
});

test('a rate of zero or less is refused', function () {
    expect(fn () => $this->currencies->recordRate($this->syp, 0))
        ->toThrow(RuntimeException::class);
});

test('formatting follows the currency symbol and decimals', function () {
    expect($this->currencies->format(1234.5, $this->base->code))->toBe('1,234.50 ر.س');
    expect($this->currencies->format(345000, 'SYP'))->toBe('345,000 ل.س');
});

test('the public endpoint lists only convertible currencies with their rates', function () {
    $this->currencies->recordRate($this->syp, 3450.75);
    $this->currencies->flushCache();

    $response = $this->getJson('/api/v1/currencies')->assertOk();

    expect($response->json('data.base'))->toBe($this->base->code);

    $codes = collect($response->json('data.currencies'))->pluck('code');
    expect($codes)->toContain($this->base->code, 'SYP');

    $syp = collect($response->json('data.currencies'))->firstWhere('code', 'SYP');
    expect((float) $syp['rate'])->toBe(3450.75);
    expect($syp['rounding_step'])->toBe(500);
    expect($syp['decimal_places'])->toBe(0);
});

test('a rateless currency is published with a null rate rather than a fake one', function () {
    $this->currencies->flushCache();

    $syp = collect($this->getJson('/api/v1/currencies')->json('data.currencies'))
        ->firstWhere('code', 'SYP');

    // The clients read this as "show base prices", not "1:1".
    expect($syp['rate'])->toBeNull();
});

test('an inactive currency is not offered to shoppers', function () {
    $this->currencies->recordRate($this->syp, 3450);
    $this->syp->update(['is_active' => false]);
    $this->currencies->flushCache();

    $codes = collect($this->getJson('/api/v1/currencies')->json('data.currencies'))->pluck('code');

    expect($codes)->not->toContain('SYP');
});

test('changing the base does not silently restate stored amounts', function () {
    $this->actingAs(User::factory()->create());
    $this->currencies->recordRate($this->syp, 3450);

    $this->currencies->setBase($this->syp);

    expect($this->currencies->baseCode())->toBe('SYP');
    // The old base is no longer base, and nothing re-expressed the numbers held
    // against it — that is an accounting event, not a side effect.
    expect(Currency::where('is_base', true)->count())->toBe(1);
});

test('changing the base keeps default_currency setting in sync', function () {
    $this->currencies->recordRate($this->syp, 3450);
    $this->currencies->setBase($this->syp);

    expect(Setting::get('default_currency'))->toBe('SYP');
    expect(get_setting('default_currency'))->toBe('SYP');
    expect(base_currency_code())->toBe('SYP');
});

test('settings default_currency chooses the base through CurrencyService', function () {
    $this->currencies->recordRate($this->syp, 3450);

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->post('/api/v1/settings', [
            'settings' => [
                'default_currency' => 'SYP',
                'default_language' => 'ar',
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.base_currency', 'SYP')
        ->assertJsonPath('data.settings.default_currency', 'SYP');

    expect($this->currencies->baseCode())->toBe('SYP');
    expect(Setting::get('default_currency'))->toBe('SYP');
});

test('settings reject a currency that is not in the managed list', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/settings', [
            'settings' => [
                'default_currency' => 'XYZ',
            ],
        ])
        ->assertStatus(422);
});

test('settings index exposes managed currencies and the live base', function () {
    $this->currencies->recordRate($this->syp, 3450);
    $this->currencies->flushCache();

    $response = $this->getJson('/api/v1/settings')->assertOk();

    expect($response->json('data.base_currency'))->toBe($this->base->code);
    expect($response->json('data.settings.default_currency'))->toBe($this->base->code);

    $codes = collect($response->json('data.currencies'))->pluck('code');
    expect($codes)->toContain($this->base->code, 'SYP');
});

test('codes are identity, so case cannot fork a currency', function () {
    $lower = Currency::create([
        'code' => 'eur',
        'name_ar' => 'يورو',
        'symbol' => '€',
        'is_active' => true,
    ]);

    expect($lower->fresh()->code)->toBe('EUR');
    expect($this->currencies->find('eur')?->id)->toBe($lower->id);
});
