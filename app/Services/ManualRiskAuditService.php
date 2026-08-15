<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;

class ManualRiskAuditService
{
    /**
     * Scan the live ERP data for manual-risk anomalies that indicate direct
     * changes outside the canonical workflow service layer.
     */
    public function scan(): array
    {
        $issues = [];

        $issues = array_merge($issues, $this->scanOrderRisks());
        $issues = array_merge($issues, $this->scanInventoryRisks());
        $issues = array_merge($issues, $this->scanFinanceRisks());

        $critical = collect($issues)->where('severity', 'critical')->count();
        $warning = collect($issues)->where('severity', 'warning')->count();

        return [
            'issues' => $issues,
            'summary' => [
                'total_issues' => count($issues),
                'critical_issues' => $critical,
                'warning_issues' => $warning,
                'last_scan' => now()->toDateTimeString(),
            ],
        ];
    }

    public function reconciliationSummary(): array
    {
        $result = $this->scan();
        $issues = $result['issues'] ?? [];
        $byCategory = collect($issues)->groupBy('type')->map(fn ($items) => count($items))->all();

        return [
            'issues' => $issues,
            'summary' => [
                'total_issues' => count($issues),
                'critical_issues' => collect($issues)->where('severity', 'critical')->count(),
                'warning_issues' => collect($issues)->where('severity', 'warning')->count(),
                'has_issues' => count($issues) > 0,
                'last_scan' => now()->toDateTimeString(),
                'by_category' => $byCategory,
            ],
        ];
    }

    protected function scanOrderRisks(): array
    {
        $issues = [];

        $orders = SalesOrder::with(['invoices', 'items', 'fulfillmentWarehouse'])
            ->whereIn('status', [SalesOrder::STATUS_SHIPPED, SalesOrder::STATUS_DELIVERED])
            ->get();

        foreach ($orders as $order) {
            $invoice = $order->invoices()->first();
            $totalInvoiced = (float) ($invoice?->total ?? 0);
            $paidAmount = (float) ($invoice?->paid_amount ?? 0);

            if ($invoice && $totalInvoiced > 0 && $paidAmount < $totalInvoiced) {
                $issues[] = [
                    'type' => 'order_finance_mismatch',
                    'severity' => 'critical',
                    'entity_type' => SalesOrder::class,
                    'entity_id' => $order->id,
                    'reference' => $order->order_number,
                    'message' => 'Order is shipped but invoice remains partially unpaid.',
                    'details' => [
                        'invoice_total' => $totalInvoiced,
                        'paid_amount' => $paidAmount,
                        'due_amount' => (float) ($invoice->due_amount ?? ($totalInvoiced - $paidAmount)),
                    ],
                ];
            }

            $totalReserved = (int) $order->items()->sum('quantity');
            $movementOut = StockMovement::where('reference', $order->order_number)
                ->where('movement_type', StockMovement::TYPE_OUT)
                ->sum('quantity');

            if ($order->status === SalesOrder::STATUS_SHIPPED && $totalReserved > 0 && $movementOut < $totalReserved) {
                $issues[] = [
                    'type' => 'order_stock_gap',
                    'severity' => 'warning',
                    'entity_type' => SalesOrder::class,
                    'entity_id' => $order->id,
                    'reference' => $order->order_number,
                    'message' => 'Shipped order has outbound stock movement below ordered quantity.',
                    'details' => [
                        'ordered_quantity' => $totalReserved,
                        'outbound_quantity' => (int) $movementOut,
                    ],
                ];
            }
        }

        return $issues;
    }

    protected function scanInventoryRisks(): array
    {
        $issues = [];

        $products = Product::with('inventory')->get();

        foreach ($products as $product) {
            $warehouseQty = (int) WarehouseInventory::where('product_id', $product->id)->sum('quantity');
            $productQty = (int) ($product->stock_quantity ?? 0);

            if ($warehouseQty !== $productQty) {
                $issues[] = [
                    'type' => 'inventory_quantity_mismatch',
                    'severity' => 'warning',
                    'entity_type' => Product::class,
                    'entity_id' => $product->id,
                    'reference' => $product->sku ?: $product->name,
                    'message' => 'Product stock quantity does not match warehouse inventory total.',
                    'details' => [
                        'product_stock_quantity' => $productQty,
                        'warehouse_inventory_quantity' => $warehouseQty,
                    ],
                ];
            }

            $lowStock = WarehouseInventory::where('product_id', $product->id)
                ->whereRaw('available_quantity - reserved_quantity <= reorder_point')
                ->exists();

            if ($lowStock && ($product->stock_quantity ?? 0) > 0) {
                $issues[] = [
                    'type' => 'inventory_low_stock',
                    'severity' => 'warning',
                    'entity_type' => Product::class,
                    'entity_id' => $product->id,
                    'reference' => $product->sku ?: $product->name,
                    'message' => 'Available stock is below the reorder threshold in at least one warehouse.',
                    'details' => [
                        'product_stock_quantity' => $productQty,
                    ],
                ];
            }
        }

        return $issues;
    }

    protected function scanFinanceRisks(): array
    {
        $issues = [];

        $invoices = Invoice::with(['payments', 'salesOrder'])->whereIn('status', [
            Invoice::STATUS_CONFIRMED,
            Invoice::STATUS_PROCESSING,
            Invoice::STATUS_SHIPPED,
            Invoice::STATUS_DELIVERED,
        ])->get();

        foreach ($invoices as $invoice) {
            $total = (float) $invoice->total;
            $paid = (float) ($invoice->paid_amount ?? 0);
            $due = (float) ($invoice->due_amount ?? ($total - $paid));

            if ($due > 0.009 && $invoice->status !== Invoice::STATUS_PENDING) {
                $issues[] = [
                    'type' => 'finance_unsettled_invoice',
                    'severity' => 'critical',
                    'entity_type' => Invoice::class,
                    'entity_id' => $invoice->id,
                    'reference' => $invoice->invoice_number,
                    'message' => 'Invoice remains unpaid while already in a non-pending workflow stage.',
                    'details' => [
                        'invoice_total' => $total,
                        'paid_amount' => $paid,
                        'due_amount' => $due,
                    ],
                ];
            }

            $paymentsTotal = (float) $invoice->payments()->sum('amount');
            if ($paymentsTotal > 0 && abs($paymentsTotal - $paid) > 0.01) {
                $issues[] = [
                    'type' => 'finance_payment_mismatch',
                    'severity' => 'warning',
                    'entity_type' => Invoice::class,
                    'entity_id' => $invoice->id,
                    'reference' => $invoice->invoice_number,
                    'message' => 'Recorded payment total differs from invoice paid_amount.',
                    'details' => [
                        'payments_total' => $paymentsTotal,
                        'invoice_paid_amount' => $paid,
                    ],
                ];
            }
        }

        return $issues;
    }
}
