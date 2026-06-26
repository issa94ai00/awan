<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = LeaveRequest::with('employee');

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($query) use ($search) {
                $query->whereHas('employee', function ($query) use ($search) {
                    $query->whereRaw('LOWER(CONCAT(first_name, \' \' , last_name)) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(department) LIKE ?', ["%{$search}%"]);
                })
                ->orWhereRaw('LOWER(leave_type) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(reason) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leaveRequests = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Leave requests retrieved successfully',
            'data' => [
                'leave_requests' => $leaveRequests->items(),
                'pagination' => [
                    'current_page' => $leaveRequests->currentPage(),
                    'last_page' => $leaveRequests->lastPage(),
                    'per_page' => $leaveRequests->perPage(),
                    'total' => $leaveRequests->total(),
                    'has_more_pages' => $leaveRequests->hasMorePages()
                ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:pending,approved,rejected',
            'reason' => 'nullable|string|max:1000',
            'approved_by' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:1000'
        ]);

        $leaveRequest = LeaveRequest::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Leave request created successfully',
            'data' => $leaveRequest
        ], 201);
    }

    public function show(LeaveRequest $leaveRequest)
    {
        $leaveRequest->load('employee');

        return response()->json([
            'success' => true,
            'message' => 'Leave request retrieved successfully',
            'data' => $leaveRequest
        ]);
    }

    public function update(Request $request, LeaveRequest $leaveRequest)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:pending,approved,rejected',
            'reason' => 'nullable|string|max:1000',
            'approved_by' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:1000'
        ]);

        $leaveRequest->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Leave request updated successfully',
            'data' => $leaveRequest
        ]);
    }

    public function destroy(LeaveRequest $leaveRequest)
    {
        $leaveRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Leave request deleted successfully',
            'data' => null
        ]);
    }
}
