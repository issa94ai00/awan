<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSearchController extends Controller
{
    /**
     * Lightweight cross-module lookup used by the admin header quick-search.
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->get('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'message' => 'Search query too short',
                'data' => [
                    'products' => [],
                    'customers' => [],
                    'invoices' => [],
                    'sales_orders' => [],
                    'employees' => [],
                    'total_results' => 0,
                ],
            ]);
        }

        $products = Product::query()
            ->whereSearch(['name_ar', 'name_en', 'sku', 'barcode'], $query)
            ->limit(5)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'type' => 'product',
                    'title' => $product->name_ar ?: $product->name_en ?: $product->name,
                    'subtitle' => $product->sku ?: '',
                    'route' => '/admin/products',
                ];
            });

        $customers = Customer::query()
            ->whereSearch(['name', 'email', 'phone'], $query)
            ->limit(5)
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'type' => 'customer',
                    'title' => $customer->name,
                    'subtitle' => $customer->email ?: $customer->phone ?: '',
                    'route' => '/admin/sales/customers',
                ];
            });

        $invoices = Invoice::query()
            ->with('customer:id,name')
            ->whereSearch(['invoice_number'], $query)
            ->limit(5)
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'type' => 'invoice',
                    'title' => $invoice->invoice_number,
                    'subtitle' => $invoice->customer?->name ?: '',
                    'route' => '/admin/sales/invoices',
                ];
            });

        $salesOrders = SalesOrder::query()
            ->with('customer:id,name')
            ->whereSearch(['order_number'], $query)
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'type' => 'sales_order',
                    'title' => $order->order_number,
                    'subtitle' => $order->customer?->name ?: '',
                    'route' => '/admin/sales/sales-orders',
                ];
            });

        $employees = Employee::query()
            ->whereSearch(['name', 'email'], $query)
            ->limit(5)
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'type' => 'employee',
                    'title' => $employee->name,
                    'subtitle' => $employee->position ?: $employee->job_title ?: '',
                    'route' => '/admin/hr/employees',
                ];
            });

        $totalResults = $products->count() + $customers->count() + $invoices->count()
            + $salesOrders->count() + $employees->count();

        return response()->json([
            'success' => true,
            'message' => 'Search results',
            'data' => [
                'products' => $products->values(),
                'customers' => $customers->values(),
                'invoices' => $invoices->values(),
                'sales_orders' => $salesOrders->values(),
                'employees' => $employees->values(),
                'total_results' => $totalResults,
            ],
        ]);
    }
}
