<?php

namespace App\Services;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowExecution;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WorkflowService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Execute a workflow
     */
    public function executeWorkflow($workflowId, array $inputData = [], $triggeredBy = null, $entityType = null, $entityId = null): WorkflowExecution
    {
        $workflow = Workflow::with('steps')->findOrFail($workflowId);

        if (!$workflow->canExecute()) {
            throw new \Exception('Workflow cannot be executed');
        }

        $execution = WorkflowExecution::create([
            'workflow_id' => $workflowId,
            'status' => WorkflowExecution::STATUS_PENDING,
            'triggered_by' => $triggeredBy,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'input_data' => $inputData,
        ]);

        try {
            $execution->markAsRunning();
            $outputData = $this->executeSteps($workflow->steps, $inputData, $execution);
            $execution->markAsCompleted($outputData);

            return $execution;
        } catch (\Exception $e) {
            $execution->markAsFailed($e->getMessage());
            Log::error("Workflow execution failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute workflow steps
     */
    protected function executeSteps($steps, array $inputData, WorkflowExecution $execution): array
    {
        $outputData = [];
        $currentOrder = 0;

        foreach ($steps as $step) {
            if ($step->order !== $currentOrder) {
                $currentOrder = $step->order;
            }

            // Check if step should execute based on condition
            if (!$step->evaluateCondition($inputData)) {
                continue;
            }

            // Execute the step
            $stepOutput = $this->executeStep($step, array_merge($inputData, $outputData), $execution);
            $outputData = array_merge($outputData, $stepOutput);

            // If step is required and failed, stop execution
            if ($step->is_required && isset($stepOutput['error'])) {
                throw new \Exception($stepOutput['error']);
            }
        }

        return $outputData;
    }

    /**
     * Execute a single step
     */
    protected function executeStep(WorkflowStep $step, array $data, WorkflowExecution $execution): array
    {
        return match($step->action_type) {
            WorkflowStep::ACTION_NOTIFICATION => $this->executeNotificationStep($step, $data),
            WorkflowStep::ACTION_EMAIL => $this->executeEmailStep($step, $data),
            WorkflowStep::ACTION_API_CALL => $this->executeApiCallStep($step, $data),
            WorkflowStep::ACTION_UPDATE_FIELD => $this->executeUpdateFieldStep($step, $data),
            WorkflowStep::ACTION_APPROVAL => $this->executeApprovalStep($step, $data, $execution),
            WorkflowStep::ACTION_TASK => $this->executeTaskStep($step, $data, $execution),
            default => [],
        };
    }

    /**
     * Execute notification step
     */
    protected function executeNotificationStep(WorkflowStep $step, array $data): array
    {
        $config = $step->action_config;
        $userId = $config['user_id'] ?? null;
        $title = $this->replaceVariables($config['title'] ?? '', $data);
        $message = $this->replaceVariables($config['message'] ?? '', $data);
        $type = $config['type'] ?? 'info';

        if ($userId) {
            $this->notificationService->sendToUser($userId, $title, $message, $type, $data);
        }

        return ['notification_sent' => true];
    }

    /**
     * Execute email step
     */
    protected function executeEmailStep(WorkflowStep $step, array $data): array
    {
        $config = $step->action_config;
        $userId = $config['user_id'] ?? null;
        $subject = $this->replaceVariables($config['subject'] ?? '', $data);
        $message = $this->replaceVariables($config['message'] ?? '', $data);

        if ($userId) {
            $this->notificationService->sendEmail($userId, $subject, $message, $data);
        }

        return ['email_sent' => true];
    }

    /**
     * Execute API call step
     */
    protected function executeApiCallStep(WorkflowStep $step, array $data): array
    {
        $config = $step->action_config;
        $url = $this->replaceVariables($config['url'] ?? '', $data);
        $method = $config['method'] ?? 'POST';
        $headers = $config['headers'] ?? [];
        $body = $config['body'] ?? [];

        try {
            $response = Http::withHeaders($headers)
                ->send($method, $url, ['json' => $body]);

            return [
                'api_call_success' => true,
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            return [
                'api_call_success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute update field step
     */
    protected function executeUpdateFieldStep(WorkflowStep $step, array $data): array
    {
        $config = $step->action_config;
        $modelClass = $config['model'] ?? null;
        $modelId = $config['model_id'] ?? null;
        $fields = $config['fields'] ?? [];

        if (!$modelClass || !$modelId) {
            return ['error' => 'Model or ID not specified'];
        }

        try {
            $model = $modelClass::find($modelId);
            if (!$model) {
                return ['error' => 'Model not found'];
            }

            foreach ($fields as $field => $value) {
                $model->$field = $this->replaceVariables($value, $data);
            }

            $model->save();

            return ['field_updated' => true];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Execute approval step
     */
    protected function executeApprovalStep(WorkflowStep $step, array $data, WorkflowExecution $execution): array
    {
        $config = $step->action_config;
        $assignedTo = $step->assigned_to;

        if ($assignedTo) {
            $this->notificationService->sendToUser(
                $assignedTo,
                'Approval Required',
                $config['message'] ?? 'Please review and approve',
                'info',
                array_merge($data, ['execution_id' => $execution->id])
            );
        }

        return ['approval_requested' => true, 'assigned_to' => $assignedTo];
    }

    /**
     * Execute task step
     */
    protected function executeTaskStep(WorkflowStep $step, array $data, WorkflowExecution $execution): array
    {
        $config = $step->action_config;
        $assignedTo = $step->assigned_to;

        if ($assignedTo) {
            $this->notificationService->sendToUser(
                $assignedTo,
                'New Task Assigned',
                $config['description'] ?? 'You have been assigned a new task',
                'info',
                array_merge($data, ['execution_id' => $execution->id])
            );
        }

        return ['task_assigned' => true, 'assigned_to' => $assignedTo];
    }

    /**
     * Replace variables in string with data
     */
    protected function replaceVariables($string, array $data): string
    {
        foreach ($data as $key => $value) {
            $string = str_replace("{{$key}}", $value, $string);
        }

        return $string;
    }

    /**
     * Trigger workflow by event
     */
    public function triggerByEvent($triggerType, array $data = [], $entityType = null, $entityId = null): array
    {
        $workflows = Workflow::active()
            ->byTrigger($triggerType)
            ->with('steps')
            ->get();

        $executions = [];

        foreach ($workflows as $workflow) {
            try {
                $execution = $this->executeWorkflow($workflow->id, $data, null, $entityType, $entityId);
                $executions[] = $execution;
            } catch (\Exception $e) {
                Log::error("Failed to trigger workflow {$workflow->id}: " . $e->getMessage());
            }
        }

        return $executions;
    }

    /**
     * Trigger order created workflow
     */
    public function triggerOrderCreated($orderId): array
    {
        return $this->triggerByEvent(
            Workflow::TRIGGER_ORDER_CREATED,
            ['order_id' => $orderId],
            'SalesOrder',
            $orderId
        );
    }

    /**
     * Trigger order status changed workflow
     */
    public function triggerOrderStatusChanged($orderId, $newStatus): array
    {
        return $this->triggerByEvent(
            Workflow::TRIGGER_ORDER_STATUS_CHANGED,
            ['order_id' => $orderId, 'status' => $newStatus],
            'SalesOrder',
            $orderId
        );
    }

    /**
     * Trigger inventory low workflow
     */
    public function triggerInventoryLow($productId, $currentStock, $minStock): array
    {
        return $this->triggerByEvent(
            Workflow::TRIGGER_INVENTORY_LOW,
            [
                'product_id' => $productId,
                'current_stock' => $currentStock,
                'min_stock' => $minStock,
            ],
            'Product',
            $productId
        );
    }

    /**
     * Get workflow execution history
     */
    public function getExecutionHistory($workflowId, $limit = 20)
    {
        return WorkflowExecution::where('workflow_id', $workflowId)
            ->latest()
            ->paginate($limit);
    }

    /**
     * Cancel running execution
     */
    public function cancelExecution($executionId): bool
    {
        $execution = WorkflowExecution::findOrFail($executionId);

        if ($execution->status !== WorkflowExecution::STATUS_RUNNING) {
            return false;
        }

        $execution->markAsCancelled();
        return true;
    }

    /**
     * Retry failed execution
     */
    public function retryExecution($executionId): WorkflowExecution
    {
        $execution = WorkflowExecution::findOrFail($executionId);

        if ($execution->status !== WorkflowExecution::STATUS_FAILED) {
            throw new \Exception('Only failed executions can be retried');
        }

        return $this->executeWorkflow(
            $execution->workflow_id,
            $execution->input_data,
            $execution->triggered_by,
            $execution->entity_type,
            $execution->entity_id
        );
    }

    /**
     * Get workflow statistics
     */
    public function getWorkflowStatistics($workflowId): array
    {
        $executions = WorkflowExecution::where('workflow_id', $workflowId);

        return [
            'total_executions' => $executions->count(),
            'completed' => $executions->where('status', WorkflowExecution::STATUS_COMPLETED)->count(),
            'failed' => $executions->where('status', WorkflowExecution::STATUS_FAILED)->count(),
            'running' => $executions->where('status', WorkflowExecution::STATUS_RUNNING)->count(),
            'cancelled' => $executions->where('status', WorkflowExecution::STATUS_CANCELLED)->count(),
            'success_rate' => $executions->count() > 0 
                ? ($executions->where('status', WorkflowExecution::STATUS_COMPLETED)->count() / $executions->count()) * 100 
                : 0,
        ];
    }
}
