<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\NumberFormat;

beforeEach(fn () => NumberFormat::forget());

test('settings store the number format choices and serve them back', function () {
    $this->actingAs(User::factory()->admin()->create(), 'sanctum')
        ->postJson('/api/v1/settings', ['settings' => [
            'number_digits' => 'latin',
            'number_decimal_separator' => ',',
            'number_thousands_separator' => '.',
            'number_rounding_mode' => 'half_even',
            'number_money_decimals' => '0',
        ]])
        ->assertSuccessful()
        ->assertJsonPath('data.settings.number_rounding_mode', 'half_even');

    expect(Setting::get('number_decimal_separator'))->toBe(',')
        ->and(format_amount(1234567.5))->toBe('1.234.568');
});

test('settings refuse an unknown number format value', function () {
    $this->actingAs(User::factory()->admin()->create(), 'sanctum')
        ->postJson('/api/v1/settings', ['settings' => ['number_rounding_mode' => 'nearest_five']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('settings.number_rounding_mode');
});

test('settings refuse a thousands separator equal to the decimal separator', function () {
    $this->actingAs(User::factory()->admin()->create(), 'sanctum')
        ->postJson('/api/v1/settings', ['settings' => [
            'number_decimal_separator' => ',',
            'number_thousands_separator' => ',',
        ]])
        ->assertStatus(422);

    expect(Setting::get('number_thousands_separator'))->toBeNull();
});

test('rounding modes treat halves the way they are named', function (string $mode, array $expected) {
    $rounded = array_map(fn ($value) => NumberFormat::round($value, 0, $mode), [2.5, 3.5, -2.5, 2.4, 2.6]);

    expect($rounded)->toEqual($expected);
})->with([
    'half up' => ['half_up', [3.0, 4.0, -3.0, 2.0, 3.0]],
    'half even' => ['half_even', [2.0, 4.0, -2.0, 2.0, 3.0]],
    'half down' => ['half_down', [2.0, 3.0, -2.0, 2.0, 3.0]],
    'ceil' => ['ceil', [3.0, 4.0, -2.0, 3.0, 3.0]],
    'floor' => ['floor', [2.0, 3.0, -3.0, 2.0, 2.0]],
    'truncate' => ['truncate', [2.0, 3.0, -2.0, 2.0, 2.0]],
]);

test('amounts follow binary-safe rounding and fold negative zero', function () {
    app()->setLocale('en');

    expect(format_amount(1.005))->toBe('1.01')
        ->and(format_amount(-0.001))->toBe('0.00')
        ->and(format_amount(-1234.5))->toBe('-1,234.50');
});
