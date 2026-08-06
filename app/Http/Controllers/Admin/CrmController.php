<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Http\Request;

class CrmController extends Controller
{
    public function index()
    {
        $customersCount = Customer::count();
        $ticketsCount = Ticket::count();
        $openTickets = Ticket::where('status', 'open')->count();

        return view('admin.crm.index', compact('customersCount', 'ticketsCount', 'openTickets'));
    }

    public function customers(Request $request)
    {
        $customers = Customer::orderBy('name')->paginate(20);

        return view('admin.crm.customers', compact('customers'));
    }

    public function tickets(Request $request)
    {
        $tickets = Ticket::with('customer')->latest()->paginate(20);

        return view('admin.crm.tickets', compact('tickets'));
    }
}
