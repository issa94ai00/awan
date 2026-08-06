<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class HrController extends Controller
{
    public function index()
    {
        $employeesCount = Employee::count();
        $leaveRequestsCount = LeaveRequest::count();
        $attendanceTodayCount = Attendance::whereDate('date', today())->count();

        return view('admin.hr.index', compact('employeesCount', 'leaveRequestsCount', 'attendanceTodayCount'));
    }

    public function employees(Request $request)
    {
        $employees = Employee::orderBy('first_name')->paginate(20);

        return view('admin.hr.employees', compact('employees'));
    }

    public function attendance(Request $request)
    {
        $attendance = Attendance::with('employee')->latest('date')->paginate(20);

        return view('admin.hr.attendance', compact('attendance'));
    }

    public function leaveRequests(Request $request)
    {
        $leaves = LeaveRequest::with('employee')->latest()->paginate(20);

        return view('admin.hr.leaves', compact('leaves'));
    }
}
