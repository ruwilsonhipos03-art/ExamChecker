<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class EmployeeController extends Controller
{
    private function orgUnitTable(): string
    {
        return Schema::hasTable('colleges') ? 'colleges' : 'departments';
    }

    private function employeeCollegeForeignKey(): string
    {
        if (Schema::hasColumn('employees', 'college_id')) {
            return 'college_id';
        }

        if (Schema::hasColumn('employees', 'department_id')) {
            return 'department_id';
        }

        return 'college_id';
    }

    public function index()
    {
        return response()->json([
            'data' => Employee::with(['user', 'department', 'office'])->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $orgTable = $this->orgUnitTable();
        $validated = $request->validate([
            'first_name'      => 'required|string|max:255',
            'middle_initial'  => 'nullable|string|max:2',
            'last_name'       => 'required|string|max:255',
            'extension_name'  => 'nullable|string|max:10',
            // Email removed from here
            'username'        => 'required|unique:users,username',
            'password'        => 'required|min:8',
            'college_id'   => 'nullable|exists:' . $orgTable . ',id',
            'office_id'       => 'nullable|exists:offices,id',
            'roles'           => 'required|array|min:1',
            'roles.*'         => 'string|in:college_dean,instructor,entrance_examiner',
        ]);

        return DB::transaction(function () use ($validated) {
            $lastEmployee = Employee::orderBy('id', 'desc')->first();
            $nextId = $lastEmployee ? ((int) str_replace('EM-', '', $lastEmployee->Employee_Number)) + 1 : 1;
            $generatedID = 'EM-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $roleString = implode(',', $validated['roles']);

            $user = User::create([
                'first_name'     => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'],
                'last_name'      => $validated['last_name'],
                'extension_name' => $validated['extension_name'],
                'username'       => $validated['username'],
                // Email removed from here
                'password'       => Hash::make($validated['password']),
                'role'           => $roleString,
            ]);

            $user->employee()->create([
                'Employee_Number' => $generatedID,
                $this->employeeCollegeForeignKey() => $validated['college_id'],
                'office_id'       => $validated['office_id'],
            ]);

            return response()->json($user->load('employee.college', 'employee.office'), 201);
        });
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);
        $user = $employee->user;
        $orgTable = $this->orgUnitTable();
        $collegeFk = $this->employeeCollegeForeignKey();

        $validated = $request->validate([
            'first_name'      => 'required|string',
            'last_name'       => 'required|string',
            // Email removed from here
            'username'        => 'required|unique:users,username,' . $user->id,
            'password'        => 'nullable|min:8',
            'college_id'   => 'nullable|exists:' . $orgTable . ',id',
            'office_id'       => 'nullable|exists:offices,id',
            'roles'           => 'required|array|min:1',
            'roles.*'         => 'string|in:college_dean,instructor,entrance_examiner',
        ]);

        DB::transaction(function () use ($validated, $employee, $user, $collegeFk) {
            $roleString = implode(',', $validated['roles']);

            $user->update([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'username'   => $validated['username'],
                'role'       => $roleString,
            ]);

            if (!empty($validated['password'])) {
                $user->update(['password' => Hash::make($validated['password'])]);
            }

            $employee->update([
                $collegeFk => $validated['college_id'],
                'office_id'       => $validated['office_id'],
            ]);
        });

        return response()->json($employee->load('user', 'department', 'office'));
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        if ($employee->user) {
            $employee->user->delete();
        }
        $employee->delete();
        return response()->json(null, 204);
    }
}
