<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function __construct(
        private AccessControlService $access,
        private AuditService $audit,
    ) {
    }

    private function createLoginAccount(array $data, ?Role $role = null): int
    {
        $role ??= Role::where('name', 'employee')->first();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => false,
            'role_id' => $role?->id,
        ]);

        return $user->id;
    }

    /**
     * The role picked for the employee's login, held to the same rules as the
     * Users & access screen: the users.assign_roles permission, nothing the
     * actor could not grant themselves, and no unsafe change to an existing
     * account. Null when no role was picked.
     */
    private function pickedLoginRole(Request $request, ?int $roleId, ?User $target = null): ?Role
    {
        if (! $roleId) {
            return null;
        }

        $actor = $request->user();
        if (! $actor->can('users.assign_roles')) {
            abort(403, 'ليس لديك صلاحية تعيين أدوار المستخدمين.');
        }

        $role = Role::with('permissions:id')->findOrFail($roleId);
        $this->access->assertCanAssignRoles($actor, collect([$role]));

        if ($target) {
            $this->access->assertCanManageUser($actor, $target);
            // The pick replaces the primary role only; other roles stay.
            $roleIds = $target->roles()->pluck('roles.id')
                ->reject(fn ($id) => (int) $id === (int) $target->role_id)
                ->push($role->id)->unique()->values()->all();
            $this->access->assertRoleChangeIsSafe($actor, $target, $roleIds);
        }

        return $role;
    }
    public function index(Request $request)
    {
        // The warehouse comes along so the list can show where each person
        // works. Without it an admin had no way to check field-app setup short
        // of opening every employee in turn.
        $query = Employee::query()->with('warehouse:id,name,code');

        if ($request->has('search') && $request->search) {
            // Word by word, so first and last name cover "first last" too.
            $query->whereSearch(['first_name', 'last_name', 'email', 'phone', 'department', 'position'], $request->search);
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
            'job_title' => 'nullable|string|max:255',
            'department' => 'required|string|max:255',
            'hire_date' => 'required|date',
            'salary' => 'nullable|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'monthly_sales_target' => 'nullable|numeric|min:0',
            'employment_type' => 'nullable|string|max:100',
            'contract_type' => 'nullable|string|max:100',
            'national_id' => 'nullable|string|max:100',
            'nationality' => 'nullable|string|max:100',
            'contract_start' => 'nullable|date',
            'contract_end' => 'nullable|date|after_or_equal:contract_start',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:100',
            'status' => 'required|in:نشط,غير نشط',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'notes' => 'nullable|string|max:1000',
            'avatar' => 'nullable|string|max:1000',
            'password' => 'nullable|string|min:8',
            // The login's primary role. Only read when the employee has, or is
            // getting, a login.
            'role_id' => 'nullable|integer|exists:roles,id',
        ]);

        $role = !empty($validated['password'])
            ? $this->pickedLoginRole($request, $validated['role_id'] ?? null)
            : null;
        unset($validated['role_id']);
        $validated = $this->fillBlankRequired($validated);

        // The login and the staff record land together: a failure creating the
        // employee used to leave a login behind with no one attached to it.
        $employee = DB::transaction(function () use ($validated, $role) {
            $userId = null;
            if (!empty($validated['password'])) {
                $userId = $this->createLoginAccount($validated, $role);
            }

            return Employee::create(array_merge($validated, ['user_id' => $userId]));
        });

        if ($role && $employee->user_id) {
            $this->logRoleChange($employee->user_id, null, $role);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully',
            'data' => $this->present($employee),
        ], 201);
    }

    public function show(Employee $employee)
    {
        $employee->load([
            'warehouse:id,name,code',
            'attendance' => fn ($q) => $q->latest('date')->limit(10),
            'leaveRequests' => fn ($q) => $q->latest()->limit(10),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Employee retrieved successfully',
            'data' => $this->present($employee),
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
            'job_title' => 'nullable|string|max:255',
            'department' => 'required|string|max:255',
            'hire_date' => 'required|date',
            'salary' => 'nullable|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'monthly_sales_target' => 'nullable|numeric|min:0',
            'employment_type' => 'nullable|string|max:100',
            'contract_type' => 'nullable|string|max:100',
            'national_id' => 'nullable|string|max:100',
            'nationality' => 'nullable|string|max:100',
            'contract_start' => 'nullable|date',
            'contract_end' => 'nullable|date|after_or_equal:contract_start',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:100',
            'status' => 'required|in:نشط,غير نشط',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'notes' => 'nullable|string|max:1000',
            'avatar' => 'nullable|string|max:1000',
            'password' => 'nullable|string|min:8',
            // The login's primary role. Only read when the employee has, or is
            // getting, a login.
            'role_id' => 'nullable|integer|exists:roles,id',
        ]);

        // A role counts only for a login that exists or is being created, and
        // only when it changes.
        $user = $employee->user;
        $roleId = $validated['role_id'] ?? null;
        unset($validated['role_id']);
        $validated = $this->fillBlankRequired($validated);
        $role = null;
        if ($roleId && ($user || !empty($validated['password'])) && (int) $roleId !== (int) $user?->role_id) {
            $role = $this->pickedLoginRole($request, (int) $roleId, $user);
        }
        $oldRole = $user?->role;

        DB::transaction(fn () => $this->saveEmployee($employee, $validated, $role));

        if ($role && $employee->user_id) {
            $this->logRoleChange($employee->user_id, $oldRole, $role);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully',
            'data' => $this->present($employee->refresh()),
        ]);
    }

    /**
     * Blank optional fields arrive as null, but these columns are NOT NULL:
     * inserting the null failed with a server error.
     */
    private function fillBlankRequired(array $validated): array
    {
        foreach (['employment_type' => '', 'contract_type' => '', 'salary' => 0, 'bonus' => 0] as $field => $blank) {
            if (array_key_exists($field, $validated) && $validated[$field] === null) {
                $validated[$field] = $blank;
            }
        }

        return $validated;
    }

    /** The employee as the form reads it: with whether and how they sign in. */
    private function present(Employee $employee): array
    {
        $user = $employee->user;
        // The login itself stays out of the payload; the form needs only these.
        $data = collect($employee->toArray())->except('user')->all();

        return $data + [
            'has_login' => $user !== null,
            'login_role_id' => $user?->role_id,
            // Roles beyond the primary one, managed from Users & access.
            'login_other_roles' => $user
                ? $user->roles()->where('roles.id', '!=', (int) $user->role_id)->pluck('display_name')->values()
                : [],
        ];
    }

    private function logRoleChange(int $userId, ?Role $old, Role $new): void
    {
        $this->audit->log(
            action: $old ? AuditLog::ACTION_UPDATE : AuditLog::ACTION_CREATE,
            entityType: User::class,
            entityId: $userId,
            description: 'تعيين الدور الأساسي من شاشة الموظفين: '.($old ? "{$old->name} ← " : '').$new->name,
            oldValues: ['primary_role_id' => $old?->id],
            newValues: ['primary_role_id' => $new->id],
            module: AuditLog::MODULE_USERS,
        );
    }

    /** Updates the staff record and its login together. */
    private function saveEmployee(Employee $employee, array $validated, ?Role $role = null): void
    {
        $roleChange = $role ? ['role_id' => $role->id] : [];

        if (!empty($validated['password'])) {
            if ($employee->user) {
                $employee->user->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                ] + $roleChange);
            } else {
                $validated['user_id'] = $this->createLoginAccount($validated, $role);
            }
        } elseif ($employee->user) {
            $employee->user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ] + $roleChange);
        }

        $employee->update($validated);
    }

    /**
     * Departments, positions and job titles already in use, so the form can
     * offer them instead of each admin spelling "المبيعات" their own way.
     */
    public function formOptions()
    {
        $distinct = fn (string $column) => Employee::query()
            ->whereNotNull($column)->where($column, '!=', '')
            ->distinct()->orderBy($column)->pluck($column)->values();

        return response()->json([
            'success' => true,
            'data' => [
                'departments' => $distinct('department'),
                'positions' => $distinct('position'),
                'job_titles' => $distinct('job_title'),
            ],
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

    public function customers(Employee $employee)
    {
        $customers = $employee->customers()->get();

        return response()->json([
            'success' => true,
            'message' => 'Customers retrieved successfully',
            'data' => $customers
        ]);
    }

    public function attachCustomers(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'exists:customers,id'
        ]);

        $employee->customers()->syncWithoutDetaching($validated['customer_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Customers attached successfully',
            'data' => $employee->customers()->get()
        ]);
    }

    public function detachCustomers(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'exists:customers,id'
        ]);

        $employee->customers()->detach($validated['customer_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Customers detached successfully',
            'data' => $employee->customers()->get()
        ]);
    }
}
