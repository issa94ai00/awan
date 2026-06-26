<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'name',
        'name_ar',
        'action_type',
        'action_config',
        'order',
        'condition_type',
        'condition_config',
        'is_parallel',
        'is_required',
        'assigned_to',
        'estimated_duration',
    ];

    protected $casts = [
        'action_config' => 'array',
        'condition_config' => 'array',
        'is_parallel' => 'boolean',
        'is_required' => 'boolean',
    ];

    const ACTION_TASK = 'task';
    const ACTION_NOTIFICATION = 'notification';
    const ACTION_EMAIL = 'email';
    const ACTION_API_CALL = 'api_call';
    const ACTION_UPDATE_FIELD = 'update_field';
    const ACTION_APPROVAL = 'approval';
    const ACTION_CONDITION = 'condition';

    const CONDITION_EQUALS = 'equals';
    const CONDITION_NOT_EQUALS = 'not_equals';
    const CONDITION_GREATER_THAN = 'greater_than';
    const CONDITION_LESS_THAN = 'less_than';
    const CONDITION_CONTAINS = 'contains';
    const CONDITION_STATUS = 'status';

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeByWorkflow($query, $workflowId)
    {
        return $query->where('workflow_id', $workflowId);
    }

    public function scopeByOrder($query, $order)
    {
        return $query->where('order', $order);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeParallel($query)
    {
        return $query->where('is_parallel', true);
    }

    public function scopeSequential($query)
    {
        return $query->where('is_parallel', false);
    }

    public function getActionTextAttribute(): string
    {
        return match($this->action_type) {
            self::ACTION_TASK => 'مهمة',
            self::ACTION_NOTIFICATION => 'إشعار',
            self::ACTION_EMAIL => 'بريد إلكتروني',
            self::ACTION_API_CALL => 'API Call',
            self::ACTION_UPDATE_FIELD => 'تحديث حقل',
            self::ACTION_APPROVAL => 'موافقة',
            self::ACTION_CONDITION => 'شرط',
            default => $this->action_type,
        };
    }

    public function evaluateCondition(array $data): bool
    {
        if (!$this->condition_type || !$this->condition_config) {
            return true;
        }

        $field = $this->condition_config['field'] ?? null;
        $value = $this->condition_config['value'] ?? null;
        $actualValue = $data[$field] ?? null;

        return match($this->condition_type) {
            self::CONDITION_EQUALS => $actualValue == $value,
            self::CONDITION_NOT_EQUALS => $actualValue != $value,
            self::CONDITION_GREATER_THAN => $actualValue > $value,
            self::CONDITION_LESS_THAN => $actualValue < $value,
            self::CONDITION_CONTAINS => str_contains($actualValue, $value),
            self::CONDITION_STATUS => $actualValue === $value,
            default => true,
        };
    }
}
