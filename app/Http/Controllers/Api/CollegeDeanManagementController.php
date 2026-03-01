<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectInstructorAssignment;
use App\Models\SubjectStudentAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CollegeDeanManagementController extends Controller
{
    private function employeeOrgUnitColumn(): string
    {
        if (Schema::hasColumn('employees', 'department_id')) {
            return 'department_id';
        }

        return 'college_id';
    }

    private function programOrgUnitColumn(): string
    {
        if (Schema::hasColumn('programs', 'department_id')) {
            return 'department_id';
        }

        return 'college_id';
    }

    private function orgUnitTable(): string
    {
        return $this->programOrgUnitColumn() === 'department_id' ? 'departments' : 'colleges';
    }

    private function orgUnitNameColumn(): string
    {
        $table = $this->orgUnitTable();
        if (Schema::hasColumn($table, 'Department_Name')) {
            return 'Department_Name';
        }

        return 'College_Name';
    }

    public function students(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $programOrgUnitColumn = $this->programOrgUnitColumn();
        $orgUnitTable = $this->orgUnitTable();
        $orgUnitNameColumn = $this->orgUnitNameColumn();

        $rows = DB::table('users as u')
            ->join('students as s', 's.user_id', '=', 'u.id')
            ->leftJoin('programs as p', 'p.id', '=', 's.program_id')
            ->leftJoin($orgUnitTable . ' as d', 'd.id', '=', 'p.' . $programOrgUnitColumn)
            ->where('u.role', 'student')
            ->where('p.' . $programOrgUnitColumn, $departmentId)
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->select([
                'u.id',
                'u.first_name',
                'u.middle_initial',
                'u.last_name',
                'u.extension_name',
                'u.username',
                'u.email',
                's.Student_Number',
                'p.id as program_id',
                'p.Program_Name as program_name',
                DB::raw('d.' . $orgUnitNameColumn . ' as College_Name'),
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'student_number' => (string) ($row->Student_Number ?? ''),
                'full_name' => $this->fullName($row),
                'username' => (string) ($row->username ?? ''),
                'email' => (string) ($row->email ?? ''),
                'program_id' => (int) ($row->program_id ?? 0),
                'program_name' => (string) ($row->program_name ?? ''),
                'College_Name' => (string) ($row->College_Name ?? ''),
            ])->values(),
        ]);
    }

    public function subjects(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        return response()->json([
            'success' => true,
            'data' => Subject::query()->orderBy('Subject_Name')->get(['id', 'Subject_Name']),
        ]);
    }

    public function instructors(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();

        $rows = User::query()
            ->whereHas('employee', function ($query) use ($departmentId, $employeeOrgUnitColumn) {
                $query->where($employeeOrgUnitColumn, $departmentId);
            })
            ->get(['id', 'first_name', 'middle_initial', 'last_name', 'extension_name', 'role'])
            ->filter(fn (User $user) => $this->hasRole($user->role, 'instructor'))
            ->values()
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'full_name' => $this->fullName($user),
                'role' => (string) $user->role,
            ]);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function studentAssignments(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $programOrgUnitColumn = $this->programOrgUnitColumn();

        $rows = SubjectStudentAssignment::query()
            ->with(['subject:id,Subject_Name', 'student:id,first_name,middle_initial,last_name,extension_name'])
            ->whereHas('student', function ($query) use ($departmentId, $programOrgUnitColumn) {
                $query->whereHas('studentProfile', function ($q) use ($departmentId, $programOrgUnitColumn) {
                    $q->whereHas('program', fn ($programQuery) => $programQuery->where($programOrgUnitColumn, $departmentId));
                });
            })
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (SubjectStudentAssignment $row) => [
                'id' => (int) $row->id,
                'subject_id' => (int) $row->subject_id,
                'subject_name' => (string) ($row->subject?->Subject_Name ?? ''),
                'student_user_id' => (int) $row->student_user_id,
                'student_name' => $this->fullName($row->student),
            ])->values(),
        ]);
    }

    public function storeStudentAssignment(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $deanUser = $context['user'];
        $programOrgUnitColumn = $this->programOrgUnitColumn();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'student_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'student')),
            ],
        ]);

        $studentIsInDepartment = DB::table('students as s')
            ->join('programs as p', 'p.id', '=', 's.program_id')
            ->where('s.user_id', (int) $validated['student_user_id'])
            ->where('p.' . $programOrgUnitColumn, $departmentId)
            ->exists();

        if (!$studentIsInDepartment) {
            return response()->json([
                'success' => false,
                'message' => 'Student is not under your college programs.',
            ], 422);
        }

        $assignment = SubjectStudentAssignment::query()->firstOrCreate([
            'subject_id' => (int) $validated['subject_id'],
            'student_user_id' => (int) $validated['student_user_id'],
        ], [
            'assigned_by_user_id' => (int) $deanUser->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => $assignment->wasRecentlyCreated ? 'Student assigned to subject.' : 'Assignment already exists.',
        ], $assignment->wasRecentlyCreated ? 201 : 200);
    }

    public function destroyStudentAssignment(Request $request, int $id)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $programOrgUnitColumn = $this->programOrgUnitColumn();

        $assignment = SubjectStudentAssignment::query()
            ->where('id', $id)
            ->whereHas('student', function ($query) use ($departmentId, $programOrgUnitColumn) {
                $query->whereHas('studentProfile', function ($q) use ($departmentId, $programOrgUnitColumn) {
                    $q->whereHas('program', fn ($programQuery) => $programQuery->where($programOrgUnitColumn, $departmentId));
                });
            })
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found in your college.',
            ], 404);
        }

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Student-subject assignment removed.',
        ]);
    }

    public function instructorAssignments(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();

        $rows = SubjectInstructorAssignment::query()
            ->with(['subject:id,Subject_Name', 'instructor:id,first_name,middle_initial,last_name,extension_name'])
            ->whereHas('instructor', function ($query) use ($departmentId, $employeeOrgUnitColumn) {
                $query->whereHas('employee', fn ($q) => $q->where($employeeOrgUnitColumn, $departmentId));
            })
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (SubjectInstructorAssignment $row) => [
                'id' => (int) $row->id,
                'subject_id' => (int) $row->subject_id,
                'subject_name' => (string) ($row->subject?->Subject_Name ?? ''),
                'instructor_user_id' => (int) $row->instructor_user_id,
                'instructor_name' => $this->fullName($row->instructor),
            ])->values(),
        ]);
    }

    public function storeInstructorAssignment(Request $request)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $deanUser = $context['user'];
        $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();

        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'instructor_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $instructor = User::query()->find((int) $validated['instructor_user_id']);
        if (!$instructor || !$this->hasRole($instructor->role, 'instructor')) {
            return response()->json([
                'success' => false,
                'message' => 'Selected user is not an instructor.',
            ], 422);
        }

        $instructorIsInDepartment = DB::table('employees')
            ->where('user_id', $instructor->id)
            ->where($employeeOrgUnitColumn, $departmentId)
            ->exists();

        if (!$instructorIsInDepartment) {
            return response()->json([
                'success' => false,
                'message' => 'Instructor is not under your college.',
            ], 422);
        }

        $assignment = SubjectInstructorAssignment::query()->firstOrCreate([
            'subject_id' => (int) $validated['subject_id'],
            'instructor_user_id' => (int) $validated['instructor_user_id'],
        ], [
            'assigned_by_user_id' => (int) $deanUser->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => $assignment->wasRecentlyCreated ? 'Instructor assigned to subject.' : 'Assignment already exists.',
        ], $assignment->wasRecentlyCreated ? 201 : 200);
    }

    public function destroyInstructorAssignment(Request $request, int $id)
    {
        $context = $this->collegeDeanContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['college_id'];
        $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();

        $assignment = SubjectInstructorAssignment::query()
            ->where('id', $id)
            ->whereHas('instructor', function ($query) use ($departmentId, $employeeOrgUnitColumn) {
                $query->whereHas('employee', fn ($q) => $q->where($employeeOrgUnitColumn, $departmentId));
            })
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found in your college.',
            ], 404);
        }

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Instructor-subject assignment removed.',
        ]);
    }

    private function collegeDeanContext(Request $request): array
    {
        $user = $request->user();
        if (!$user || !$this->hasRole($user->role, 'college_dean')) {
            return [
                'error' => response()->json([
                    'success' => false,
                    'message' => 'Only college deans can access this resource.',
                ], 403),
            ];
        }

        $departmentId = (int) ($user->employee?->college_id ?? $user->employee?->department_id ?? 0);
        if ($departmentId <= 0) {
            return [
                'error' => response()->json([
                    'success' => false,
                    'message' => 'College dean does not have an assigned college.',
                ], 422),
            ];
        }

        return [
            'error' => null,
            'user' => $user,
            'college_id' => $departmentId,
        ];
    }

    private function hasRole(?string $roles, string $role): bool
    {
        if (!$roles) {
            return false;
        }

        $roleList = array_map('trim', explode(',', $roles));
        return in_array($role, $roleList, true);
    }

    private function fullName($row): string
    {
        if (!$row) {
            return '';
        }

        $first = trim((string) ($row->first_name ?? ''));
        $middle = trim((string) ($row->middle_initial ?? ''));
        $last = trim((string) ($row->last_name ?? ''));
        $ext = trim((string) ($row->extension_name ?? ''));

        $parts = array_values(array_filter([$last, $first]));
        $name = implode(', ', $parts);

        if ($middle !== '') {
            $name .= ($name !== '' ? ' ' : '') . $middle;
        }
        if ($ext !== '') {
            $name .= ($name !== '' ? ' ' : '') . $ext;
        }

        return trim($name);
    }
}
