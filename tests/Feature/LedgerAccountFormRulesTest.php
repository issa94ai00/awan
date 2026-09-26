<?php

use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;

/**
 * What the chart-of-accounts form may and may not write.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->url = '/api/v1/admin/accounting/ledger-accounts';
});

it('stores the type lowercase so posting treats the account by its normal side', function () {
    $created = $this->actingAs($this->admin)->postJson($this->url, [
        'code' => '9101', 'name' => 'صندوق فرعي', 'type' => 'Asset',
    ])->assertCreated()->json('data');

    expect($created['type'])->toBe('asset');
    // Debit-normal: a debit raises it.
    expect(LedgerAccount::signedDelta($created['type'], 100, 0))->toBe(100.0);
});

it('refuses a type that is not one of the five', function () {
    $this->actingAs($this->admin)->postJson($this->url, [
        'code' => '9102', 'name' => 'x', 'type' => 'cash',
    ])->assertStatus(422)->assertJsonValidationErrors('type');
});

it('never takes a balance from the form', function () {
    $created = $this->actingAs($this->admin)->postJson($this->url, [
        'code' => '9103', 'name' => 'x', 'type' => 'asset', 'balance' => 5000,
    ])->assertCreated()->json('data');

    expect((float) LedgerAccount::find($created['id'])->balance)->toBe(0.0);

    LedgerAccount::whereKey($created['id'])->update(['balance' => 250]);

    // A stale figure sent back by an edit form must not overwrite the ledger's.
    $this->actingAs($this->admin)->putJson($this->url.'/'.$created['id'], [
        'code' => '9103', 'name' => 'renamed', 'type' => 'asset', 'balance' => 0,
    ])->assertOk();

    expect((float) LedgerAccount::find($created['id'])->balance)->toBe(250.0);
});

it('files an account under a parent of the same type only', function () {
    $assets = LedgerAccount::create(['code' => '9200', 'name' => 'أصول', 'type' => 'asset', 'balance' => 0]);

    $child = $this->actingAs($this->admin)->postJson($this->url, [
        'code' => '9201', 'name' => 'فرعي', 'type' => 'asset', 'parent_id' => $assets->id,
    ])->assertCreated()->json('data');
    expect($child['parent_id'])->toBe($assets->id);

    $this->actingAs($this->admin)->postJson($this->url, [
        'code' => '9202', 'name' => 'التزام', 'type' => 'liability', 'parent_id' => $assets->id,
    ])->assertStatus(422);
});

it('refuses to make an account a child of itself or of its own sub-account', function () {
    $top = LedgerAccount::create(['code' => '9300', 'name' => 'a', 'type' => 'asset', 'balance' => 0]);
    $mid = LedgerAccount::create(['code' => '9301', 'name' => 'b', 'type' => 'asset', 'balance' => 0, 'parent_id' => $top->id]);

    $this->actingAs($this->admin)->putJson($this->url.'/'.$top->id, [
        'code' => '9300', 'name' => 'a', 'type' => 'asset', 'parent_id' => $top->id,
    ])->assertStatus(422);

    $this->actingAs($this->admin)->putJson($this->url.'/'.$top->id, [
        'code' => '9300', 'name' => 'a', 'type' => 'asset', 'parent_id' => $mid->id,
    ])->assertStatus(422);

    expect($top->fresh()->parent_id)->toBeNull();
});
