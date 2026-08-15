<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminPagesController extends Controller
{
    public function employeeCustomers()
    {
        return view('admin.hr.employee-customers');
    }

    public function professionalSalesReport()
    {
        return view('admin.reports.professional-sales');
    }
}
