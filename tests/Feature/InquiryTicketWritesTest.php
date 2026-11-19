<?php

use App\Models\Customer;
use App\Models\Inquiry;
use App\Models\InquiryReply;
use App\Models\Ticket;
use App\Models\User;

function iqCreate(array $overrides = []): Inquiry
{
    return Inquiry::create(array_merge([
        'name' => 'زائر', 'email' => 'v@example.com', 'phone' => '0944000111', 'subject' => 'other',
        'message' => 'استفسار', 'status' => Inquiry::STATUS_NEW,
    ], $overrides));
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

// ---- public submission endpoints ----

it('stores a public inquiry through the API and validates required fields', function () {
    $this->postJson('/api/v1/inquiries', ['name' => 'x'])
        ->assertStatus(422)->assertJsonPath('success', false);
    expect(Inquiry::count())->toBe(0);

    $this->postJson('/api/v1/inquiries', [
        'name' => 'سامر', 'email' => 's@example.com', 'phone' => '0955123456', 'subject' => 'سعر', 'message' => 'كم السعر؟',
    ]);

    // The row is written before the response is built, so assert the stored effect.
    $inquiry = Inquiry::first();
    expect($inquiry->name)->toBe('سامر')->and($inquiry->status)->toBe(Inquiry::STATUS_NEW);
});

it('stores an inquiry from the public web form and redirects back to the form', function () {
    $this->post('/inquiry', ['name' => 'a'])->assertSessionHasErrors(['phone', 'subject', 'message']);
    expect(Inquiry::count())->toBe(0);

    $this->post('/inquiry', [
        'name' => 'ليلى', 'email' => 'l@example.com', 'phone' => '0966123456', 'subject' => 'توصيل', 'message' => 'هل يوجد توصيل؟',
    ])->assertRedirect(route('inquiry.create'))->assertSessionHas('success');

    expect(Inquiry::where('name', 'ليلى')->where('status', Inquiry::STATUS_NEW)->exists())->toBeTrue();
});

it('acknowledges the public contact form', function () {
    $this->post('/contact', ['name' => 'x', 'message' => 'hi'])
        ->assertRedirect(route('contact'))->assertSessionHas('success');
});

// ---- admin inquiry writes ----

it('requires authentication for admin inquiry writes', function () {
    $inquiry = iqCreate();
    $id = $inquiry->id;

    $this->putJson("/api/v1/admin/inquiries/$id", ['status' => 'read'])->assertStatus(401);
    $this->deleteJson("/api/v1/admin/inquiries/$id")->assertStatus(401);
    expect(Inquiry::find($id))->not->toBeNull();
});

it('updates an inquiry and validates enums', function () {
    $inquiry = iqCreate();
    $id = $inquiry->id;

    $this->actingAs($this->admin)->putJson("/api/v1/admin/inquiries/$id", ['status' => 'bogus'])->assertStatus(422);
    $this->actingAs($this->admin)->putJson("/api/v1/admin/inquiries/$id", ['status' => 'read', 'priority' => 'high'])->assertOk();

    $inquiry->refresh();
    expect($inquiry->status)->toBe('read')->and($inquiry->priority)->toBe('high');
});

it('deletes an inquiry', function () {
    $id = iqCreate()->id;

    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/inquiries/$id")->assertOk();
    expect(Inquiry::find($id))->toBeNull();
});

it('assigns an inquiry to an existing user only', function () {
    $inquiry = iqCreate();
    $id = $inquiry->id;

    $this->actingAs($this->admin)->postJson("/api/v1/admin/inquiries/$id/assign", [])->assertStatus(422);
    $this->actingAs($this->admin)->postJson("/api/v1/admin/inquiries/$id/assign", ['assigned_to' => 999999])->assertStatus(422);
    $this->actingAs($this->admin)->postJson("/api/v1/admin/inquiries/$id/assign", ['assigned_to' => $this->admin->id])->assertOk();

    expect($inquiry->fresh()->assigned_to)->toBe($this->admin->id);
});

it('closes and reopens an inquiry', function () {
    $inquiry = iqCreate();
    $id = $inquiry->id;

    $this->actingAs($this->admin)->postJson("/api/v1/admin/inquiries/$id/close")->assertOk();
    expect($inquiry->fresh()->closed_at)->not->toBeNull();

    $this->actingAs($this->admin)->postJson("/api/v1/admin/inquiries/$id/reopen")->assertOk();
    expect($inquiry->fresh()->closed_at)->toBeNull();
});

it('records an admin reply and marks the inquiry replied', function () {
    $inquiry = iqCreate();
    $id = $inquiry->id;

    $this->actingAs($this->admin)->postJson("/api/v1/admin/inquiries/$id/replies", [])->assertStatus(422);
    $this->actingAs($this->admin)->postJson("/api/v1/admin/inquiries/$id/replies", ['message' => 'شكراً لتواصلك'])->assertCreated();

    $reply = InquiryReply::first();
    expect($reply->inquiry_id)->toBe($id)
        ->and($reply->admin_id)->toBe($this->admin->id)
        ->and($reply->message)->toBe('شكراً لتواصلك')
        ->and($inquiry->fresh()->status)->toBe(Inquiry::STATUS_REPLIED);
});

it('bulk-updates and bulk-deletes only the selected inquiries', function () {
    $a = iqCreate();
    $b = iqCreate();
    $c = iqCreate();

    $this->actingAs($this->admin)->postJson('/api/v1/admin/inquiries/bulk-update', ['inquiry_ids' => [999999], 'status' => 'read'])->assertStatus(422);

    $this->actingAs($this->admin)->postJson('/api/v1/admin/inquiries/bulk-update', [
        'inquiry_ids' => [$a->id, $b->id], 'status' => 'read', 'priority' => 'urgent',
    ])->assertOk()->assertJsonPath('data.updated_count', 2);

    expect($a->fresh()->status)->toBe('read')->and($b->fresh()->priority)->toBe('urgent')
        ->and($c->fresh()->status)->toBe('new');

    $this->actingAs($this->admin)->postJson('/api/v1/admin/inquiries/bulk-delete', ['inquiry_ids' => [$a->id, $b->id]])
        ->assertOk()->assertJsonPath('data.deleted_count', 2);

    expect(Inquiry::pluck('id')->all())->toBe([$c->id]);
});

// ---- tickets ----

function tkPayload(Customer $customer, array $overrides = []): array
{
    return array_merge([
        'customer_id' => $customer->id, 'subject' => 'عطل', 'message' => 'الجهاز لا يعمل',
        'status' => 'open', 'priority' => 'medium',
    ], $overrides);
}

it('creates a ticket stamped with the creator and validates enums', function () {
    $customer = Customer::create(['name' => 'عميل', 'phone' => '0977000111', 'status' => 'active']);

    $this->actingAs($this->admin)->postJson('/api/v1/tickets', tkPayload($customer, ['priority' => 'critical']))->assertStatus(422);
    $this->actingAs($this->admin)->postJson('/api/v1/tickets', ['subject' => 'x'])->assertStatus(422);
    expect(Ticket::count())->toBe(0);

    $this->actingAs($this->admin)->postJson('/api/v1/tickets', tkPayload($customer))->assertCreated();

    $ticket = Ticket::first();
    expect($ticket->customer_id)->toBe($customer->id)->and($ticket->created_by)->toBe($this->admin->id)
        ->and($ticket->status)->toBe('open');
});

it('updates and deletes a ticket', function () {
    $customer = Customer::create(['name' => 'عميل', 'phone' => '0977000222', 'status' => 'active']);
    $ticket = Ticket::create(tkPayload($customer) + ['created_by' => $this->admin->id]);
    $id = $ticket->id;

    $this->actingAs($this->admin)->putJson("/api/v1/tickets/$id", tkPayload($customer, ['status' => 'nope']))->assertStatus(422);
    $this->actingAs($this->admin)->putJson("/api/v1/tickets/$id", tkPayload($customer, ['status' => 'resolved', 'priority' => 'high', 'assigned_to' => $this->admin->id]))->assertOk();

    $ticket->refresh();
    expect($ticket->status)->toBe('resolved')->and($ticket->priority)->toBe('high')->and($ticket->assigned_to)->toBe($this->admin->id);

    $this->actingAs($this->admin)->deleteJson("/api/v1/tickets/$id")->assertOk();
    expect(Ticket::find($id))->toBeNull();
});
