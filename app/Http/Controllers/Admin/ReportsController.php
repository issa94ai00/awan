<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Employee;
use App\Models\Ticket;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function index()
    {
        $salesRevenue = Invoice::where('status', Invoice::STATUS_PAID)->sum('total');
        $openInvoices = Invoice::where('status', Invoice::STATUS_PENDING)->count();
        $purchaseOrders = PurchaseOrder::count();
        $employeeCount = Employee::count();
        $openTickets = Ticket::where('status', 'open')->count();

        return view('admin.reports.index', compact(
            'salesRevenue',
            'openInvoices',
            'purchaseOrders',
            'employeeCount',
            'openTickets'
        ));
    }

    public function export()
    {
        return view('admin.reports.export');
    }
}
