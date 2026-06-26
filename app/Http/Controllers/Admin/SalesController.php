<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Customer;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function index()
    {
        $invoicesCount = Invoice::count();
        $customersCount = Customer::count();
        $revenue = Invoice::where('status', Invoice::STATUS_PAID)->sum('total');

        return view('admin.sales.index', compact('invoicesCount', 'customersCount', 'revenue'));
    }

    public function invoices(Request $request)
    {
        $invoices = Invoice::latest()->paginate(20);

        return view('admin.sales.invoices', compact('invoices'));
    }

    public function customers(Request $request)
    {
        $customers = Customer::orderBy('name')->paginate(20);

        return view('admin.sales.customers', compact('customers'));
    }
}
