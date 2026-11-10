<?php

use App\Models\Quote;
use App\Models\Setting;
use App\Models\User;
use App\Support\DocumentNumbers;

beforeEach(fn () => DocumentNumbers::forget());

test('document numbers carry the default prefixes until one is configured', function () {
    expect(DocumentNumbers::format('invoice', 7))->toBe('INV-000007')
        ->and(DocumentNumbers::format('fixed_asset', 7, 5))->toBe('FA-00007')
        ->and(DocumentNumbers::lead('rma'))->toBe('RMA-');
});

test('settings store a document prefix and the next number follows it', function () {
    $this->actingAs(User::factory()->admin()->create(), 'sanctum')
        ->postJson('/api/v1/settings', ['settings' => [
            'doc_prefix_quote' => 'QUO',
            'doc_prefix_invoice' => 'فاتورة',
        ]])
        ->assertSuccessful()
        ->assertJsonPath('data.settings.doc_prefix_quote', 'QUO');

    DocumentNumbers::forget();

    expect(DocumentNumbers::prefix('quote'))->toBe('QUO')
        ->and(DocumentNumbers::format('invoice', 1))->toBe('فاتورة-000001')
        ->and(Quote::nextNumber())->toStartWith('QUO-')
        // Untouched types keep their defaults.
        ->and(DocumentNumbers::prefix('sales_order'))->toBe('SO');
});

test('settings refuse a prefix with spaces or a separator', function (string $bad) {
    $this->actingAs(User::factory()->admin()->create(), 'sanctum')
        ->postJson('/api/v1/settings', ['settings' => ['doc_prefix_invoice' => $bad]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('settings.doc_prefix_invoice');

    expect(Setting::get('doc_prefix_invoice'))->toBeNull();
})->with(['IN V', 'INV-', 'A-VERY-LONG-PREFIX']);

test('an emptied prefix returns to the default', function () {
    Setting::set('doc_prefix_invoice', 'SI');
    DocumentNumbers::forget();
    expect(DocumentNumbers::prefix('invoice'))->toBe('SI');

    $this->actingAs(User::factory()->admin()->create(), 'sanctum')
        ->postJson('/api/v1/settings', ['settings' => ['doc_prefix_invoice' => '']])
        ->assertSuccessful();

    DocumentNumbers::forget();
    expect(Setting::get('doc_prefix_invoice'))->toBeNull()
        ->and(DocumentNumbers::prefix('invoice'))->toBe('INV');
});
