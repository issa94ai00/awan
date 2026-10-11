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
            // Word by word, so first and last name cover "first last" too.
            $query->whereSearch([
                'employee.first_name', 'employee.last_name', 'employee.department',
                'leave_type', 'status', 'reason',
            ], $request->search);
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

        if ($validated['status'] === 'approved' && empty($validated['approved_by'])) {
            $validated['approved_by'] = auth()->id();
        }

        $leaveRequest = LeaveRequest::create($validated);
        $leaveRequest->load('employee');

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
            'employee_id' => 'sometimes|required|exists:employees,id',
            'leave_type' => 'sometimes|required|string|max:255',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'status' => 'sometimes|required|in:pending,approved,rejected',
            'reason' => 'nullable|string|max:1000',
            'approved_by' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:1000'
        ]);

        if (isset($validated['status']) && $validated['status'] === 'approved' && empty($validated['approved_by'])) {
            $validated['approved_by'] = auth()->id();
        }

        $leaveRequest->update($validated);
        $leaveRequest->load('employee');

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
