<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\RmaItem;
use App\Models\RmaRequest;
use App\Models\User;
use App\Models\Warehouse;

function rmawAdmin(): User
{
    return User::factory()->admin()->create();
}

/** Creates a pending RMA through the real store endpoint. */
function rmawCreate($test, User $admin, string $resolution = 'refund'): RmaRequest
{
    $customer = Customer::create(['name' => 'عميل', 'phone' => '0911'.random_int(100000, 999999), 'status' => 'active']);
    $product = Product::factory()->create(['price' => 100, 'cost_price' => 60]);
    $invoice = Invoice::create([
        'invoice_number' => 'INV-RW-'.uniqid(),
        'customer_id' => $customer->id,
        'subtotal' => 400, 'total' => 400, 'status' => 'confirmed',
    ]);
    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id, 'product_id' => $product->id, 'product_name' => 'منتج',
        'quantity' => 4, 'unit_price' => 100, 'total_price' => 400,
    ]);
    $warehouse = Warehouse::first() ?? Warehouse::create(['name' => 'WH', 'code' => 'WH-RW', 'is_active' => true]);

    $test->actingAs($admin)->postJson('/api/v1/admin/rma', [
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'warehouse_id' => $warehouse->id,
        'reason' => 'defective',
        'type' => 'refund',
        'items' => [[
            'invoice_item_id' => $item->id,
            'quantity_requested' => 2,
            'condition' => 'new',
            'resolution' => $resolution,
        ]],
    ])->assertCreated();

    return RmaRequest::latest('id')->first();
}

it('approves a pending RMA and refuses a second approval', function () {
    $admin = rmawAdmin();
    $rma = rmawCreate($this, $admin);
    $id = $rma->id;

    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/approve", ['admin_notes' => 'ok'])->assertOk();

    $rma->refresh();
    expect($rma->status)->toBe(RmaRequest::STATUS_APPROVED)
        ->and($rma->approved_by)->toBe($admin->id)
        ->and($rma->admin_notes)->toBe('ok');

    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/approve")->assertStatus(422);
});

it('rejects a pending RMA with a required reason', function () {
    $admin = rmawAdmin();
    $rma = rmawCreate($this, $admin);
    $id = $rma->id;

    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/reject", [])->assertStatus(422);
    expect($rma->fresh()->status)->toBe(RmaRequest::STATUS_PENDING);

    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/reject", ['reason' => 'خارج الضمان'])->assertOk();
    expect($rma->fresh()->status)->toBe(RmaRequest::STATUS_REJECTED);

    // A rejected request can no longer be approved or rejected again.
    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/approve")->assertStatus(422);
    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/reject", ['reason' => 'x'])->assertStatus(422);
});

it('cancels an open RMA', function () {
    $admin = rmawAdmin();
    $rma = rmawCreate($this, $admin);
    $id = $rma->id;

    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/cancel")->assertOk();
    expect($rma->fresh()->status)->toBe(RmaRequest::STATUS_CANCELLED);
});

it('refuses to cancel or delete a completed RMA', function () {
    $admin = rmawAdmin();
    $rma = rmawCreate($this, $admin);
    $rma->forceFill(['status' => RmaRequest::STATUS_COMPLETED])->save();
    $id = $rma->id;

    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/cancel")->assertStatus(422);
    $this->actingAs($admin)->deleteJson("/api/v1/admin/rma/$id")->assertStatus(422);

    expect($rma->fresh()->status)->toBe(RmaRequest::STATUS_COMPLETED);
});

it('refuses receive and complete before approval', function () {
    $admin = rmawAdmin();
    $rma = rmawCreate($this, $admin);
    $id = $rma->id;
    $itemId = $rma->items()->first()->id;

    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/receive", [
        'items' => [['rma_item_id' => $itemId, 'quantity_received' => 1]],
    ])->assertStatus(422);
    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/complete")->assertStatus(422);

    expect($rma->items()->first()->quantity_received)->toBe(0)
        ->and($rma->fresh()->status)->toBe(RmaRequest::STATUS_PENDING);
});

it('receives approved goods and refuses to complete refund lines until received', function () {
    $admin = rmawAdmin();
    $rma = rmawCreate($this, $admin);
    $id = $rma->id;
    $itemId = $rma->items()->first()->id;

    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/approve")->assertOk();

    // Refund line not yet received: completing is refused.
    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/complete")->assertStatus(422);

    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/receive", [
        'items' => [['rma_item_id' => $itemId, 'quantity_received' => 2]],
    ])->assertOk();

    expect(RmaItem::find($itemId)->quantity_received)->toBe(2)
        ->and($rma->fresh()->status)->toBe(RmaRequest::STATUS_RECEIVED);

    // Received goods block deletion until the count is corrected to zero.
    $this->actingAs($admin)->deleteJson("/api/v1/admin/rma/$id")->assertStatus(422);
    expect(RmaRequest::find($id))->not->toBeNull();
});

it('completes a received RMA', function () {
    $admin = rmawAdmin();
    $rma = rmawCreate($this, $admin);
    $id = $rma->id;
    $itemId = $rma->items()->first()->id;

    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/approve")->assertOk();
    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/receive", [
        'items' => [['rma_item_id' => $itemId, 'quantity_received' => 2]],
    ])->assertOk();

    $res = $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/complete", ['refund_method' => 'cash']);
    $res->assertOk();
    expect($rma->fresh()->status)->toBe(RmaRequest::STATUS_COMPLETED)
        ->and((float) $rma->fresh()->refund_amount)->toBe(200.0);
});

it('updates a pending RMA but not an approved one', function () {
    $admin = rmawAdmin();
    $rma = rmawCreate($this, $admin);
    $id = $rma->id;

    $this->actingAs($admin)->putJson("/api/v1/admin/rma/$id", [
        'reason_description' => 'تفاصيل جديدة',
        'admin_notes' => 'ملاحظة',
    ])->assertOk();

    expect($rma->fresh()->reason_description)->toBe('تفاصيل جديدة');

    $this->actingAs($admin)->postJson("/api/v1/admin/rma/$id/approve")->assertOk();
    $this->actingAs($admin)->putJson("/api/v1/admin/rma/$id", ['reason_description' => 'متأخر'])->assertStatus(422);
    expect($rma->fresh()->reason_description)->toBe('تفاصيل جديدة');
});

it('updates an RMA item and validates the condition enum', function () {
    $admin = rmawAdmin();
    $rma = rmawCreate($this, $admin);
    $itemId = $rma->items()->first()->id;

    $this->actingAs($admin)->putJson("/api/v1/admin/rma/items/$itemId", ['condition' => 'bogus'])->assertStatus(422);

    $this->actingAs($admin)->putJson("/api/v1/admin/rma/items/$itemId", [
        'condition' => 'damaged', 'notes' => 'مكسور',
    ])->assertOk();

    $item = RmaItem::find($itemId);
    expect($item->condition)->toBe('damaged')->and($item->notes)->toBe('مكسور');
});

it('deletes an unreceived RMA', function () {
    $admin = rmawAdmin();
    $rma = rmawCreate($this, $admin);
    $id = $rma->id;

    $this->actingAs($admin)->deleteJson("/api/v1/admin/rma/$id")->assertOk();
    expect(RmaRequest::find($id))->toBeNull();
});
