<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    private function createLoginAccount(array $data): int
    {
        $role = Role::where('name', 'employee')->first();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => false,
            'role_id' => $role?->id,
        ]);

        return $user->id;
    }
    public function index(Request $request)
    {
        // The warehouse comes along so the list can show where each person
        // works. Without it an admin had no way to check field-app setup short
        // of opening every employee in turn.
        $query = Employee::query()->with('warehouse:id,name,code');

        if ($request->has('search') && $request->search) {
            $search = strtolower($request->search);
            $query->whereRaw("LOWER(CONCAT(first_name, ' ', last_name)) LIKE ?", ["%{$search}%"])
                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(department) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(position) LIKE ?', ["%{$search}%"]);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $employees = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Employees retrieved successfully',
            'data' => [
                'employees' => $employees->items(),
                'pagination' => [
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                    'per_page' => $employees->perPage(),
                    'total' => $employees->total(),
                    'has_more_pages' => $employees->hasMorePages(),
                ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'unique:employees,email', 'unique:users,email'],
            'phone' => 'required|string|max:50',
            'position' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'hire_date' => 'required|date',
            'salary' => 'nullable|numeric|min:0',
            'status' => 'required|in:نشط,غير نشط',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'notes' => 'nullable|string|max:1000',
            'avatar' => 'nullable|url',
            'password' => 'nullable|string|min:8'
        ]);

        $userId = null;
        if (!empty($validated['password'])) {
            $userId = $this->createLoginAccount($validated);
        }

        $employee = Employee::create(array_merge($validated, ['user_id' => $userId]));

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully',
            'data' => $employee
        ], 201);
    }

    public function show(Employee $employee)
    {
        $employee->load('warehouse:id,name,code');

        return response()->json([
            'success' => true,
            'message' => 'Employee retrieved successfully',
            // Whether this person can sign in at all. The edit form shows an
            // empty password box either way, so without this an admin could not
            // tell a staff member who has a login from one who has never had
            // one — and "why can't they open the app?" had no answer on screen.
            'data' => $employee->toArray() + ['has_login' => $employee->user_id !== null],
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $userId = $employee->user_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required', 'email',
                'unique:employees,email,' . $employee->id,
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => 'required|string|max:50',
            'position' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'hire_date' => 'required|date',
            'salary' => 'nullable|numeric|min:0',
            'status' => 'required|in:نشط,غير نشط',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'notes' => 'nullable|string|max:1000',
            'avatar' => 'nullable|url',
            'password' => 'nullable|string|min:8'
        ]);

        if (!empty($validated['password'])) {
            if ($employee->user) {
                $employee->user->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                ]);
            } else {
                $validated['user_id'] = $this->createLoginAccount($validated);
            }
        } elseif ($employee->user) {
            $employee->user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);
        }

        $employee->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully',
            'data' => $employee
        ]);
    }

    public function destroy(Employee $employee)
    {
        $user = $employee->user;

        $employee->delete();

        if ($user && !$user->is_admin) {
            $user->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully',
            'data' => null
        ]);
    }

    public function salesEmployees()
    {
        $employees = Employee::orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        return response()->json([
            'success' => true,
            'data' => $employees->map(fn($e) => [
                'id' => $e->id,
                'name' => $e->name,
            ]),
        ]);
    }
}
