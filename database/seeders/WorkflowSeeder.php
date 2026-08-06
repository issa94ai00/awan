<?php

namespace Database\Seeders;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Database\Seeder;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // Order Confirmation Workflow
        $orderWorkflow = Workflow::firstOrCreate(
            [
                'name' => 'Order Confirmation Process',
                'trigger_type' => 'order_created',
            ],
            [
                'name_ar' => 'عملية تأكيد الطلب',
                'description' => 'Automatically sends confirmation and processes new orders',
                'trigger_config' => ['auto_execute' => true],
                'status' => 'active',
                'created_by' => 1,
            ]
        );

        // Add steps to order workflow
        $this->createWorkflowSteps($orderWorkflow, [
            [
                'name' => 'Send Confirmation Email',
                'name_ar' => 'إرسال بريد التأكيد',
                'action_type' => 'notification',
                'action_config' => [
                    'template_key' => 'order_confirmed',
                    'channel' => 'email',
                ],
                'order' => 1,
                'is_required' => true,
            ],
            [
                'name' => 'Update Inventory',
                'name_ar' => 'تحديث المخزون',
                'action_type' => 'update_field',
                'action_config' => [
                    'model' => 'WarehouseInventory',
                    'field' => 'stock_quantity',
                    'operation' => 'subtract',
                ],
                'order' => 2,
                'is_required' => true,
            ],
            [
                'name' => 'Notify Warehouse Team',
                'name_ar' => 'إبلاغ فريق المستودع',
                'action_type' => 'notification',
                'action_config' => [
                    'template_key' => 'new_order_warehouse',
                    'channel' => 'in_app',
                    'target_role' => 'warehouse_manager',
                ],
                'order' => 3,
                'is_required' => false,
            ],
        ]);

        // Low Stock Alert Workflow
        $lowStockWorkflow = Workflow::firstOrCreate(
            [
                'name' => 'Low Stock Alert',
                'trigger_type' => 'inventory_low',
            ],
            [
                'name_ar' => 'تنبيه انخفاض المخزون',
                'description' => 'Alerts when inventory falls below minimum level',
                'trigger_config' => ['auto_execute' => true],
                'status' => 'active',
                'created_by' => 1,
            ]
        );

        $this->createWorkflowSteps($lowStockWorkflow, [
            [
                'name' => 'Send Low Stock Alert',
                'name_ar' => 'إرسال تنبيه انخفاض المخزون',
                'action_type' => 'notification',
                'action_config' => [
                    'template_key' => 'low_stock_alert',
                    'channel' => 'email',
                    'target_role' => 'inventory_manager',
                ],
                'order' => 1,
                'is_required' => true,
            ],
            [
                'name' => 'Create Purchase Order Request',
                'name_ar' => 'إنشاء طلب شراء',
                'action_type' => 'task',
                'action_config' => [
                    'task_type' => 'purchase_request',
                    'priority' => 'high',
                ],
                'order' => 2,
                'is_required' => false,
            ],
        ]);

        // Order Status Change Workflow
        $statusChangeWorkflow = Workflow::firstOrCreate(
            [
                'name' => 'Order Status Update Notifications',
                'trigger_type' => 'order_status_changed',
            ],
            [
                'name_ar' => 'إشعارات تحديث حالة الطلب',
                'description' => 'Sends notifications when order status changes',
                'trigger_config' => ['auto_execute' => true],
                'status' => 'active',
                'created_by' => 1,
            ]
        );

        $this->createWorkflowSteps($statusChangeWorkflow, [
            [
                'name' => 'Send Status Notification',
                'name_ar' => 'إرسال إشعار الحالة',
                'action_type' => 'notification',
                'action_config' => [
                    'template_key' => 'order_status_update',
                    'channel' => 'email',
                ],
                'order' => 1,
                'is_required' => true,
                'condition_type' => 'status',
                'condition_config' => [
                    'field' => 'status',
                    'value' => 'shipped',
                ],
            ],
        ]);

        $this->command->info('Created sample workflows');
    }

    protected function createWorkflowSteps(Workflow $workflow, array $steps): void
    {
        foreach ($steps as $stepData) {
            WorkflowStep::updateOrCreate(
                [
                    'workflow_id' => $workflow->id,
                    'name' => $stepData['name'],
                    'order' => $stepData['order'],
                ],
                $stepData
            );
        }
    }
}
