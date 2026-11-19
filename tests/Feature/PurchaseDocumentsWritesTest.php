<?php

use App\Models\PurchaseReceipt;
use App\Models\PurchaseReturn;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);
    $this->actingAs($this->admin);

    $this->supplier = Supplier::create(['name' => 'مورد', 'status' => 'active', 'balance' => 0]);
    $this->receipt = PurchaseReceipt::create([
        'receipt_number' => 'PR-W1',
        'supplier_id' => $this->supplier->id,
        'receipt_date' => '2026-01-10',
        'status' => 'received',
        'notes' => 'قديم',
    ]);
});

it('updates a receipt\'s header fields only', function () {
    $id = $this->receipt->id;

    $this->putJson("/api/v1/purchase-receipts/$id", ['notes' => 'ملاحظة جديدة', 'receipt_date' => '2026-01-12'])->assertOk();
    $fresh = $this->receipt->fresh();
    expect($fresh->notes)->toBe('ملاحظة جديدة')->and($fresh->receipt_date->toDateString())->toBe('2026-01-12')
        ->and($fresh->status)->toBe('received');

    $this->putJson("/api/v1/purchase-receipts/$id", ['notes' => str_repeat('x', 1001)])->assertStatus(422);
    $this->putJson("/api/v1/purchase-receipts/$id", ['purchase_order_id' => 999999])->assertStatus(422);
});

it('refuses editing the lines of a received receipt', function () {
    $id = $this->receipt->id;

    $this->putJson("/api/v1/purchase-receipts/$id", ['items' => [['product_id' => 1, 'quantity' => 5]], 'notes' => 'تغيير'])->assertStatus(422);
    expect($this->receipt->fresh()->notes)->toBe('قديم')->and($this->receipt->items()->count())->toBe(0);
});

it('refuses deleting a receipt whose goods entered stock', function () {
    $id = $this->receipt->id;

    $this->deleteJson("/api/v1/purchase-receipts/$id")->assertStatus(422)->assertJsonPath('success', false);
    expect(PurchaseReceipt::find($id))->not->toBeNull();
});

it('refuses deleting a purchase return, and shuts out a non-admin', function () {
    $return = PurchaseReturn::create([
        'return_number' => 'PRT-W1', 'supplier_id' => $this->supplier->id,
        'purchase_receipt_id' => $this->receipt->id, 'return_date' => '2026-01-15',
        'credit_amount' => 10, 'status' => 'completed',
    ]);
    $id = $return->id;

    $this->deleteJson("/api/v1/admin/purchase-returns/$id")->assertStatus(422)->assertJsonPath('success', false);
    expect(PurchaseReturn::find($id))->not->toBeNull();

    $role = Role::firstOrCreate(['name' => 'sales'], ['display_name' => 'sales']);
    $this->actingAs(User::factory()->create(['is_admin' => false, 'role_id' => $role->id]));
    $this->deleteJson("/api/v1/admin/purchase-returns/$id")->assertForbidden();
    expect(PurchaseReturn::find($id))->not->toBeNull();
});
