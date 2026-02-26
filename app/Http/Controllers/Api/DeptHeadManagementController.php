<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectInstructorAssignment;
use App\Models\SubjectStudentAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeptHeadManagementController extends Controller
{
    public function students(Request $request)
    {
        $context = $this->deptHeadContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['department_id'];

        $rows = DB::table('users as u')
            ->join('students as s', 's.user_id', '=', 'u.id')
            ->leftJoin('programs as p', 'p.id', '=', 's.program_id')
            ->leftJoin('departments as d', 'd.id', '=', 'p.department_id')
            ->where('u.role', 'student')
            ->where('p.department_id', $departmentId)
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
                'd.Department_Name as department_name',
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
                'department_name' => (string) ($row->department_name ?? ''),
            ])->values(),
        ]);
    }

    public function subjects(Request $request)
    {
        $context = $this->deptHeadContext($request);
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
        $context = $this->deptHeadContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['department_id'];

        $rows = User::query()
            ->whereHas('employee', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
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
        $context = $this->deptHeadContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['department_id'];

        $rows = SubjectStudentAssignment::query()
            ->with(['subject:id,Subject_Name', 'student:id,first_name,middle_initial,last_name,extension_name'])
            ->whereHas('student', function ($query) use ($departmentId) {
                $query->whereHas('studentProfile', function ($q) use ($departmentId) {
                    $q->whereHas('program', fn ($programQuery) => $programQuery->where('department_id', $departmentId));
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
        $context = $this->deptHeadContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['department_id'];
        $deanUser = $context['user'];

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
            ->where('p.department_id', $departmentId)
            ->exists();

        if (!$studentIsInDepartment) {
            return response()->json([
                'success' => false,
                'message' => 'Student is not under your department programs.',
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
        $context = $this->deptHeadContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['department_id'];

        $assignment = SubjectStudentAssignment::query()
            ->where('id', $id)
            ->whereHas('student', function ($query) use ($departmentId) {
                $query->whereHas('studentProfile', function ($q) use ($departmentId) {
                    $q->whereHas('program', fn ($programQuery) => $programQuery->where('department_id', $departmentId));
                });
            })
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found in your department.',
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
        $context = $this->deptHeadContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['department_id'];

        $rows = SubjectInstructorAssignment::query()
            ->with(['subject:id,Subject_Name', 'instructor:id,first_name,middle_initial,last_name,extension_name'])
            ->whereHas('instructor', function ($query) use ($departmentId) {
                $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
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
        $context = $this->deptHeadContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['department_id'];
        $deanUser = $context['user'];

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
            ->where('department_id', $departmentId)
            ->exists();

        if (!$instructorIsInDepartment) {
            return response()->json([
                'success' => false,
                'message' => 'Instructor is not under your department.',
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
        $context = $this->deptHeadContext($request);
        if ($context['error']) {
            return $context['error'];
        }

        $departmentId = $context['department_id'];

        $assignment = SubjectInstructorAssignment::query()
            ->where('id', $id)
            ->whereHas('instructor', function ($query) use ($departmentId) {
                $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
            })
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found in your department.',
            ], 404);
        }

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Instructor-subject assignment removed.',
        ]);
    }

    private function deptHeadContext(Request $request): array
    {
        $user = $request->user();
        if (!$user || !$this->hasRole($user->role, 'dept_head')) {
            return [
                'error' => response()->json([
                    'success' => false,
                    'message' => 'Only college deans can access this resource.',
                ], 403),
            ];
        }

        $departmentId = (int) ($user->employee?->department_id ?? 0);
        if ($departmentId <= 0) {
            return [
                'error' => response()->json([
                    'success' => false,
                    'message' => 'College dean does not have an assigned department.',
                ], 422),
            ];
        }

        return [
            'error' => null,
            'user' => $user,
            'department_id' => $departmentId,
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

