<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowExecution;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    protected WorkflowService $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    public function index(Request $request)
    {
        $query = Workflow::with(['creator', 'steps']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->trigger_type) {
            $query->byTrigger($request->trigger_type);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function show($id)
    {
        $workflow = Workflow::with(['creator', 'steps.assignedUser'])->findOrFail($id);

        return response()->json($workflow);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'name_ar' => 'nullable|string',
            'description' => 'nullable|string',
            'trigger_type' => 'required|in:manual,order_created,order_status_changed,inventory_low,scheduled,webhook',
            'trigger_config' => 'nullable|array',
            'status' => 'in:active,inactive,draft',
            'metadata' => 'nullable|array',
        ]);

        $validated['created_by'] = auth()->id();

        $workflow = Workflow::create($validated);

        return response()->json($workflow, 201);
    }

    public function update(Request $request, $id)
    {
        $workflow = Workflow::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string',
            'name_ar' => 'nullable|string',
            'description' => 'nullable|string',
            'trigger_type' => 'in:manual,order_created,order_status_changed,inventory_low,scheduled,webhook',
            'trigger_config' => 'nullable|array',
            'status' => 'in:active,inactive,draft',
            'metadata' => 'nullable|array',
        ]);

        $workflow->update($validated);

        return response()->json($workflow);
    }

    public function destroy($id)
    {
        $workflow = Workflow::findOrFail($id);
        $workflow->delete();

        return response()->json(['message' => 'Workflow deleted']);
    }

    public function execute(Request $request, $id)
    {
        $validated = $request->validate([
            'input_data' => 'nullable|array',
        ]);

        $execution = $this->workflowService->executeWorkflow(
            $id,
            $validated['input_data'] ?? [],
            auth()->id()
        );

        return response()->json($execution, 201);
    }

    public function getExecutions($id, Request $request)
    {
        $workflow = Workflow::findOrFail($id);
        $executions = $this->workflowService->getExecutionHistory($id, $request->limit ?? 20);

        return response()->json($executions);
    }

    public function getStatistics($id)
    {
        $workflow = Workflow::findOrFail($id);
        $statistics = $this->workflowService->getWorkflowStatistics($id);

        return response()->json($statistics);
    }

    public function cancelExecution($executionId)
    {
        $success = $this->workflowService->cancelExecution($executionId);

        if (!$success) {
            return response()->json(['message' => 'Cannot cancel this execution'], 400);
        }

        return response()->json(['message' => 'Execution cancelled']);
    }

    public function retryExecution($executionId)
    {
        try {
            $execution = $this->workflowService->retryExecution($executionId);
            return response()->json($execution, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function indexSteps($workflowId)
    {
        $workflow = Workflow::findOrFail($workflowId);
        $steps = WorkflowStep::where('workflow_id', $workflowId)
            ->with('assignedUser')
            ->orderBy('order')
            ->get();

        return response()->json($steps);
    }

    public function storeStep(Request $request, $workflowId)
    {
        $workflow = Workflow::findOrFail($workflowId);

        $validated = $request->validate([
            'name' => 'required|string',
            'name_ar' => 'nullable|string',
            'action_type' => 'required|in:task,notification,email,api_call,update_field,approval,condition',
            'action_config' => 'required|array',
            'order' => 'integer',
            'condition_type' => 'nullable|in:equals,not_equals,greater_than,less_than,contains,status',
            'condition_config' => 'nullable|array',
            'is_parallel' => 'boolean',
            'is_required' => 'boolean',
            'assigned_to' => 'nullable|exists:users,id',
            'estimated_duration' => 'nullable|integer',
        ]);

        $validated['workflow_id'] = $workflowId;

        $step = WorkflowStep::create($validated);

        return response()->json($step, 201);
    }

    public function updateStep(Request $request, $id)
    {
        $step = WorkflowStep::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string',
            'name_ar' => 'nullable|string',
            'action_type' => 'in:task,notification,email,api_call,update_field,approval,condition',
            'action_config' => 'array',
            'order' => 'integer',
            'condition_type' => 'nullable|in:equals,not_equals,greater_than,less_than,contains,status',
            'condition_config' => 'nullable|array',
            'is_parallel' => 'boolean',
            'is_required' => 'boolean',
            'assigned_to' => 'nullable|exists:users,id',
            'estimated_duration' => 'nullable|integer',
        ]);

        $step->update($validated);

        return response()->json($step);
    }

    public function destroyStep($id)
    {
        $step = WorkflowStep::findOrFail($id);
        $step->delete();

        return response()->json(['message' => 'Step deleted']);
    }

    public function reorderSteps(Request $request, $workflowId)
    {
        $validated = $request->validate([
            'steps' => 'required|array',
            'steps.*.id' => 'required|exists:workflow_steps,id',
            'steps.*.order' => 'required|integer',
        ]);

        foreach ($validated['steps'] as $stepData) {
            $step = WorkflowStep::findOrFail($stepData['id']);
            $step->order = $stepData['order'];
            $step->save();
        }

        return response()->json(['message' => 'Steps reordered']);
    }
}
