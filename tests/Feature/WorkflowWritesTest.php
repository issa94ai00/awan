<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStep;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin']);
    $this->admin = User::factory()->create(['is_admin' => true, 'role_id' => $role->id]);
    Sanctum::actingAs($this->admin);
});

function wf(array $o = []): Workflow
{
    return Workflow::create($o + ['name' => 'WF', 'trigger_type' => 'manual', 'status' => 'active']);
}

function wfStep(Workflow $w, int $order = 1, string $name = 'S'): WorkflowStep
{
    return WorkflowStep::create([
        'workflow_id' => $w->id, 'name' => $name, 'action_type' => 'task', 'action_config' => [], 'order' => $order,
    ]);
}

it('updates and deletes a workflow', function () {
    $id = wf()->id;

    $this->putJson("/api/v1/workflows/$id", ['status' => 'archived'])->assertStatus(422);
    $this->putJson("/api/v1/workflows/$id", ['name' => 'Renamed', 'status' => 'inactive'])->assertOk();
    $this->assertDatabaseHas('workflows', ['id' => $id, 'name' => 'Renamed', 'status' => 'inactive']);

    $this->deleteJson("/api/v1/workflows/$id")->assertOk();
    expect(Workflow::find($id))->toBeNull();
    $this->deleteJson("/api/v1/workflows/$id")->assertNotFound();
});

it('executes an active workflow and records the execution', function () {
    $w = wf();
    $id = $w->id;
    wfStep($w);

    $this->postJson("/api/v1/workflows/$id/execute", ['input_data' => 'nope'])->assertStatus(422);
    $execId = $this->postJson("/api/v1/workflows/$id/execute", ['input_data' => ['a' => 1]])
        ->assertCreated()->json('id');

    $exec = WorkflowExecution::find($execId);
    expect($exec->workflow_id)->toBe($id)
        ->and($exec->triggered_by)->toBe($this->admin->id)
        ->and($exec->input_data)->toBe(['a' => 1]);
    expect($exec->status)->toBeIn([WorkflowExecution::STATUS_COMPLETED, WorkflowExecution::STATUS_RUNNING]);

    $this->postJson('/api/v1/workflows/999999/execute')->assertNotFound();
});

it('adds, updates, reorders and deletes steps', function () {
    $w = wf();
    $wid = $w->id;

    $this->postJson("/api/v1/workflows/$wid/steps", ['name' => 'x'])->assertStatus(422);
    $this->postJson('/api/v1/workflows/999999/steps', ['name' => 'x', 'action_type' => 'task', 'action_config' => []])->assertNotFound();

    $a = $this->postJson("/api/v1/workflows/$wid/steps", [
        'name' => 'First', 'action_type' => 'task', 'action_config' => ['k' => 'v'], 'order' => 1,
    ])->assertCreated()->json('id');
    $b = $this->postJson("/api/v1/workflows/$wid/steps", [
        'name' => 'Second', 'action_type' => 'notification', 'action_config' => ['m' => 1], 'order' => 2,
    ])->assertCreated()->json('id');
    $this->assertDatabaseHas('workflow_steps', ['id' => $a, 'workflow_id' => $wid, 'name' => 'First']);

    $this->putJson("/api/v1/workflows/steps/$a", ['name' => 'First!', 'action_type' => 'bogus'])->assertStatus(422);
    $this->putJson("/api/v1/workflows/steps/$a", ['name' => 'First!'])->assertOk();
    $this->assertDatabaseHas('workflow_steps', ['id' => $a, 'name' => 'First!']);

    $this->postJson("/api/v1/workflows/$wid/steps/reorder", [])->assertStatus(422);
    $this->postJson("/api/v1/workflows/$wid/steps/reorder", ['steps' => [['id' => 999999, 'order' => 1]]])->assertStatus(422);
    $this->postJson("/api/v1/workflows/$wid/steps/reorder", ['steps' => [['id' => $a, 'order' => 2], ['id' => $b, 'order' => 1]]])->assertOk();
    $this->assertDatabaseHas('workflow_steps', ['id' => $a, 'order' => 2]);
    $this->assertDatabaseHas('workflow_steps', ['id' => $b, 'order' => 1]);

    $this->deleteJson("/api/v1/workflows/steps/$a")->assertOk();
    expect(WorkflowStep::find($a))->toBeNull();
    $this->deleteJson("/api/v1/workflows/steps/$a")->assertNotFound();
});

it('cancels only running executions', function () {
    $w = wf();
    $running = WorkflowExecution::create(['workflow_id' => $w->id, 'status' => 'running', 'input_data' => []]);
    $done = WorkflowExecution::create(['workflow_id' => $w->id, 'status' => 'completed', 'input_data' => []]);
    $rid = $running->id;
    $did = $done->id;

    $this->postJson("/api/v1/workflows/executions/$rid/cancel")->assertOk();
    $this->assertDatabaseHas('workflow_executions', ['id' => $rid, 'status' => 'cancelled']);

    $this->postJson("/api/v1/workflows/executions/$did/cancel")->assertStatus(400);
    $this->assertDatabaseHas('workflow_executions', ['id' => $did, 'status' => 'completed']);
    $this->postJson('/api/v1/workflows/executions/999999/cancel')->assertNotFound();
});

it('retries only failed executions by creating a new one', function () {
    $w = wf();
    wfStep($w);
    $failed = WorkflowExecution::create(['workflow_id' => $w->id, 'status' => 'failed', 'input_data' => ['x' => 1], 'error_message' => 'boom']);
    $done = WorkflowExecution::create(['workflow_id' => $w->id, 'status' => 'completed', 'input_data' => []]);
    $fid = $failed->id;
    $did = $done->id;

    $this->postJson("/api/v1/workflows/executions/$did/retry")->assertStatus(400);
    expect(WorkflowExecution::count())->toBe(2);

    $newId = $this->postJson("/api/v1/workflows/executions/$fid/retry")->assertCreated()->json('id');
    expect($newId)->not->toBe($fid);
    expect(WorkflowExecution::find($newId)->workflow_id)->toBe($w->id);
    expect(WorkflowExecution::find($newId)->input_data)->toBe(['x' => 1]);
    $this->assertDatabaseHas('workflow_executions', ['id' => $fid, 'status' => 'failed']);
});

it('forbids non-admins from every workflow write', function () {
    $w = wf();
    $s = wfStep($w);
    $wid = $w->id;
    $sid = $s->id;
    $exec = WorkflowExecution::create(['workflow_id' => $wid, 'status' => 'running', 'input_data' => []]);
    $eid = $exec->id;

    Sanctum::actingAs(User::factory()->create(['is_admin' => false]));

    $this->putJson("/api/v1/workflows/$wid", ['name' => 'Hacked'])->assertForbidden();
    $this->deleteJson("/api/v1/workflows/$wid")->assertForbidden();
    $this->postJson("/api/v1/workflows/$wid/execute")->assertForbidden();
    $this->postJson("/api/v1/workflows/$wid/steps", ['name' => 'x', 'action_type' => 'task', 'action_config' => []])->assertForbidden();
    $this->postJson("/api/v1/workflows/$wid/steps/reorder", ['steps' => [['id' => $sid, 'order' => 9]]])->assertForbidden();
    $this->putJson("/api/v1/workflows/steps/$sid", ['name' => 'Hacked'])->assertForbidden();
    $this->deleteJson("/api/v1/workflows/steps/$sid")->assertForbidden();
    $this->postJson("/api/v1/workflows/executions/$eid/cancel")->assertForbidden();
    $this->postJson("/api/v1/workflows/executions/$eid/retry")->assertForbidden();

    $this->assertDatabaseHas('workflows', ['id' => $wid, 'name' => 'WF']);
    $this->assertDatabaseHas('workflow_steps', ['id' => $sid, 'order' => 1]);
    $this->assertDatabaseHas('workflow_executions', ['id' => $eid, 'status' => 'running']);
});
