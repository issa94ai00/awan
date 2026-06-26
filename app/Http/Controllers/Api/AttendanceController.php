<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::with('employee');

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($query) use ($search) {
                $query->whereHas('employee', function ($query) use ($search) {
                    $query->whereRaw('LOWER(CONCAT(first_name, \' \' , last_name)) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(department) LIKE ?', ["%{$search}%"]);
                })
                ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(notes) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->where('date', $request->date);
        }

        $attendance = $query->latest('date')->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Attendance records retrieved successfully',
            'data' => [
                'attendance' => $attendance->items(),
                'pagination' => [
                    'current_page' => $attendance->currentPage(),
                    'last_page' => $attendance->lastPage(),
                    'per_page' => $attendance->perPage(),
                    'total' => $attendance->total(),
                    'has_more_pages' => $attendance->hasMorePages()
                ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'clock_in' => 'nullable|date_format:Y-m-d H:i:s',
            'clock_out' => 'nullable|date_format:Y-m-d H:i:s',
            'status' => 'required|in:present,late,absent,remote',
            'notes' => 'nullable|string|max:1000'
        ]);

        $attendance = Attendance::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Attendance record created successfully',
            'data' => $attendance
        ], 201);
    }

    public function show(Attendance $attendance)
    {
        $attendance->load('employee');

        return response()->json([
            'success' => true,
            'message' => 'Attendance record retrieved successfully',
            'data' => $attendance
        ]);
    }

    public function update(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'clock_in' => 'nullable|date_format:Y-m-d H:i:s',
            'clock_out' => 'nullable|date_format:Y-m-d H:i:s',
            'status' => 'required|in:present,late,absent,remote',
            'notes' => 'nullable|string|max:1000'
        ]);

        $attendance->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Attendance record updated successfully',
            'data' => $attendance
        ]);
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attendance record deleted successfully',
            'data' => null
        ]);
    }
}
