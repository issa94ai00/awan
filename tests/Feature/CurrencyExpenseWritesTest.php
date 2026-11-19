<?php

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Expense;
use App\Models\JournalEntryHeader;
use App\Models\Role;
use App\Models\User;
use App\Services\CurrencyService;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);
    $this->actingAs($this->admin);
    app(CurrencyService::class)->flushCache();

    $this->base = Currency::where('is_base', true)->firstOrFail();
    $this->syp = Currency::where('code', 'SYP')->firstOrFail();
});

it('updates a currency but never changes its code, and protects the base from deactivation', function () {
    $id = $this->syp->id;
    $this->putJson("/api/v1/admin/currencies/$id", ['name_ar' => 'ليرة معدلة', 'code' => 'XXX', 'sort_order' => 7])->assertOk();

    $fresh = $this->syp->fresh();
    expect($fresh->name_ar)->toBe('ليرة معدلة')->and($fresh->code)->toBe('SYP')->and($fresh->sort_order)->toBe(7);

    $this->putJson("/api/v1/admin/currencies/$id", ['is_active' => false])->assertOk();
    expect($this->syp->fresh()->is_active)->toBeFalse();

    $baseId = $this->base->id;
    $this->putJson("/api/v1/admin/currencies/$baseId", ['is_active' => false])->assertStatus(422);
    expect($this->base->fresh()->is_active)->toBeTrue();
});

it('records a rate, refuses a non-positive rate and a non-1 rate on the base', function () {
    $id = $this->syp->id;
    $before = CurrencyRate::where('currency_id', $id)->count();

    $this->postJson("/api/v1/admin/currencies/$id/rates", ['rate' => 13100, 'note' => 'اختبار'])->assertCreated();
    $row = CurrencyRate::where('currency_id', $id)->orderByDesc('id')->first();
    expect(CurrencyRate::where('currency_id', $id)->count())->toBe($before + 1)
        ->and((float) $row->rate)->toBe(13100.0)->and($row->note)->toBe('اختبار')
        ->and($row->created_by)->toBe($this->admin->id);

    $this->postJson("/api/v1/admin/currencies/$id/rates", ['rate' => 0])->assertStatus(422);

    $baseId = $this->base->id;
    $this->postJson("/api/v1/admin/currencies/$baseId/rates", ['rate' => 3])->assertStatus(422);
    expect(CurrencyRate::where('currency_id', $baseId)->where('rate', 3)->exists())->toBeFalse();
});

it('moves the base currency and is idempotent for the current base', function () {
    $oldBaseId = $this->base->id;
    $this->postJson("/api/v1/admin/currencies/$oldBaseId/base")->assertOk();
    expect($this->base->fresh()->is_base)->toBeTrue();

    $newId = $this->syp->id;
    $this->postJson("/api/v1/admin/currencies/$newId/base")->assertOk()->assertJsonPath('data.base', 'SYP');
    expect($this->syp->fresh()->is_base)->toBeTrue()->and($this->base->fresh()->is_base)->toBeFalse();
    expect(Currency::where('is_base', true)->count())->toBe(1);
    expect(CurrencyRate::where('currency_id', $newId)->where('base_code', 'SYP')->where('rate', 1)->exists())->toBeTrue();
});

it('updates an expense and re-posts the ledger when the amount changes', function () {
    $created = $this->postJson('/api/v1/expenses', [
        'description' => 'شحن', 'amount' => 100, 'category' => 'shipping',
        'expense_date' => now()->toDateString(), 'status' => 'paid',
    ])->assertSuccessful();
    $id = $created->json('data.id');
    $entries = JournalEntryHeader::count();

    $this->putJson("/api/v1/expenses/$id", ['description' => 'شحن معدل', 'amount' => 250])->assertOk();
    $e = Expense::find($id);
    expect($e->description)->toBe('شحن معدل')->and((float) $e->amount)->toBe(250.0);
    // Correction reverses the old entry and posts a new one.
    expect(JournalEntryHeader::count())->toBeGreaterThan($entries);

    $this->putJson("/api/v1/expenses/$id", ['category' => 'bogus'])->assertStatus(422);
    $this->putJson("/api/v1/expenses/$id", ['amount' => 0])->assertStatus(422);
    expect((float) Expense::find($id)->amount)->toBe(250.0);
});

it('updates a metadata-only expense without touching the ledger', function () {
    $e = Expense::create([
        'expense_number' => 'EXP-W1', 'description' => 'تغليف', 'amount' => 80, 'category' => 'packaging',
        'status' => 'pending', 'expense_date' => now()->toDateString(),
    ]);
    $id = $e->id;
    $entries = JournalEntryHeader::count();

    $this->putJson("/api/v1/expenses/$id", ['notes' => 'ملاحظة'])->assertOk();
    expect($e->fresh()->notes)->toBe('ملاحظة')->and(JournalEntryHeader::count())->toBe($entries);
});

it('deletes an expense and reverses its ledger entry', function () {
    $created = $this->postJson('/api/v1/expenses', [
        'description' => 'مناولة', 'amount' => 60, 'category' => 'handling',
        'expense_date' => now()->toDateString(), 'status' => 'paid',
    ])->assertSuccessful();
    $id = $created->json('data.id');

    $this->deleteJson("/api/v1/expenses/$id")->assertOk();
    expect(Expense::find($id))->toBeNull();
    $this->deleteJson("/api/v1/expenses/$id")->assertNotFound();
});
