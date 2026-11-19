<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;

function hrwEmployee(): Employee
{
    return Employee::create([
        'first_name' => 'Hr', 'last_name' => 'Emp', 'email' => 'hr.'.uniqid().'@example.com',
        'phone' => '09'.random_int(10000000, 99999999), 'status' => 'active',
    ]);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->employee = hrwEmployee();
});

it('records an attendance row and validates employee and status', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/attendance', [
        'employee_id' => 999999, 'date' => '2026-03-01', 'status' => 'present',
    ])->assertStatus(422)->assertJsonValidationErrors('employee_id');
    $this->actingAs($this->admin)->postJson('/api/v1/admin/attendance', [
        'employee_id' => $this->employee->id, 'date' => '2026-03-01', 'status' => 'sleeping',
    ])->assertStatus(422)->assertJsonValidationErrors('status');
    expect(Attendance::count())->toBe(0);

    $this->actingAs($this->admin)->postJson('/api/v1/admin/attendance', [
        'employee_id' => $this->employee->id, 'date' => '2026-03-01',
        'clock_in' => '2026-03-01 08:05:00', 'status' => 'late', 'notes' => 'ازدحام',
    ])->assertCreated();

    $row = Attendance::first();
    expect($row->employee_id)->toBe($this->employee->id)->and($row->status)->toBe('late')->and($row->notes)->toBe('ازدحام');
});

it('updates and deletes an attendance row', function () {
    $row = Attendance::create(['employee_id' => $this->employee->id, 'date' => '2026-03-02', 'status' => 'present']);
    $id = $row->id;

    $this->actingAs($this->admin)->putJson("/api/v1/admin/attendance/$id", ['status' => 'bogus'])->assertStatus(422);
    $this->actingAs($this->admin)->putJson("/api/v1/admin/attendance/$id", [
        'status' => 'remote', 'clock_out' => '2026-03-02 16:00:00',
    ])->assertOk();

    $row->refresh();
    expect($row->status)->toBe('remote')->and($row->clock_out)->not->toBeNull()
        ->and($row->employee_id)->toBe($this->employee->id);

    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/attendance/$id")->assertOk();
    expect(Attendance::count())->toBe(0);
});

it('creates a leave request, rejects an inverted range, and stamps the approver on approval', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/leave-requests', [
        'employee_id' => $this->employee->id, 'leave_type' => 'annual',
        'start_date' => '2026-04-10', 'end_date' => '2026-04-01', 'status' => 'pending',
    ])->assertStatus(422)->assertJsonValidationErrors('end_date');
    expect(LeaveRequest::count())->toBe(0);

    $this->actingAs($this->admin)->postJson('/api/v1/admin/leave-requests', [
        'employee_id' => $this->employee->id, 'leave_type' => 'annual',
        'start_date' => '2026-04-01', 'end_date' => '2026-04-05', 'status' => 'pending',
    ])->assertCreated();
    expect(LeaveRequest::first()->approved_by)->toBeNull();

    $this->actingAs($this->admin)->postJson('/api/v1/admin/leave-requests', [
        'employee_id' => $this->employee->id, 'leave_type' => 'sick',
        'start_date' => '2026-05-01', 'end_date' => '2026-05-02', 'status' => 'approved',
    ])->assertCreated();

    $approved = LeaveRequest::where('leave_type', 'sick')->first();
    expect($approved->status)->toBe('approved')->and($approved->approved_by)->toBe($this->admin->id);
});

it('updates a leave request, stamping the approver, and deletes it', function () {
    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id, 'leave_type' => 'annual',
        'start_date' => '2026-06-01', 'end_date' => '2026-06-03', 'status' => 'pending',
    ]);
    $id = $leave->id;

    $this->actingAs($this->admin)->putJson("/api/v1/admin/leave-requests/$id", ['status' => 'maybe'])->assertStatus(422);

    $this->actingAs($this->admin)->putJson("/api/v1/admin/leave-requests/$id", ['status' => 'approved', 'notes' => 'تمت الموافقة'])->assertOk();

    $leave->refresh();
    expect($leave->status)->toBe('approved')->and($leave->approved_by)->toBe($this->admin->id)->and($leave->notes)->toBe('تمت الموافقة');

    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/leave-requests/$id")->assertOk();
    expect(LeaveRequest::count())->toBe(0);
});

it('rejects unauthenticated HR writes', function () {
    $this->postJson('/api/v1/admin/attendance', [])->assertStatus(401);
    $this->postJson('/api/v1/admin/leave-requests', [])->assertStatus(401);
});
