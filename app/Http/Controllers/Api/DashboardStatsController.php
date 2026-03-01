<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardStatsController extends Controller
{
    private const PASSING_SCORE = 75;

    private function employeeCollegeColumn(): ?string
    {
        if (Schema::hasColumn('employees', 'college_id')) {
            return 'college_id';
        }

        if (Schema::hasColumn('employees', 'department_id')) {
            return 'department_id';
        }

        return null;
    }

    private function programCollegeColumn(): ?string
    {
        if (Schema::hasColumn('programs', 'college_id')) {
            return 'college_id';
        }

        if (Schema::hasColumn('programs', 'department_id')) {
            return 'department_id';
        }

        return null;
    }

    private function orgUnitTable(): ?string
    {
        if (Schema::hasTable('colleges')) {
            return 'colleges';
        }

        if (Schema::hasTable('departments')) {
            return 'departments';
        }

        return null;
    }

    public function admin()
    {
        $orgUnitTable = $this->orgUnitTable();

        $totalEmployees = DB::table('users')
            ->where('role', '!=', 'admin') // Exclude the main admin
            ->where(function ($query) {
                $query->whereRaw("FIND_IN_SET('college_dean', role) > 0")
                    ->orWhereRaw("FIND_IN_SET('instructor', role) > 0")
                    ->orWhereRaw("FIND_IN_SET('entrance_examiner', role) > 0");
            })
            ->count();

        return response()->json([
            'total_employees' => $totalEmployees,
            'total_students' => DB::table('users')->where('role', 'student')->count(),
            'colleges' => $orgUnitTable ? DB::table($orgUnitTable)->count() : 0,
            'programs' => DB::table('programs')->count(),
        ]);
    }

    public function collegeDean()
    {
        $userId = Auth::id();
        $collegeColumn = $this->employeeCollegeColumn();
        $programCollegeColumn = $this->programCollegeColumn();

        if (!$collegeColumn || !$programCollegeColumn) {
            return response()->json([
                'message' => 'Required college assignment columns are missing from employees/programs tables.',
            ], 422);
        }

        $employee = DB::table('employees')
            ->where('user_id', $userId)
            ->selectRaw("id, {$collegeColumn} as college_id")
            ->first();
        $departmentId = $employee?->college_id;
        $employeeId = $employee?->id;

        $examsCreated = DB::table('exams')
            ->where(function ($query) use ($employeeId, $userId) {
                if ($employeeId) {
                    $query->where('created_by', $employeeId)
                        ->orWhere('created_by', $userId);
                    return;
                }

                $query->where('created_by', $userId);
            })
            ->count();

        $subjects = DB::table('exam_subjects')
            ->where('user_id', $userId)
            ->distinct('subject_id')
            ->count('subject_id');

        $studentIdsQuery = DB::table('students')
            ->join('programs', 'programs.id', '=', 'students.program_id')
            ->where("programs.{$programCollegeColumn}", $departmentId)
            ->select('students.user_id')
            ->distinct();

        $totalExaminees = (clone $studentIdsQuery)->count();

        $checkedSheets = DB::table('answer_sheets')
            ->whereIn('user_id', $studentIdsQuery)
            ->where('status', 'checked')
            ->whereNotNull('total_score')
            ->count();

        $passedSheets = DB::table('answer_sheets')
            ->whereIn('user_id', $studentIdsQuery)
            ->where('status', 'checked')
            ->where('total_score', '>=', self::PASSING_SCORE)
            ->count();

        return response()->json([
            'exams_created' => $examsCreated,
            'total_examinees' => $totalExaminees,
            'subjects' => $subjects,
            'passing_rate' => $this->asPercent($passedSheets, $checkedSheets),
        ]);
    }

    public function entrance()
    {
        $takenStudentIds = DB::table('student_exam_schedules')
            ->whereIn('status', ['attended', 'missed'])
            ->distinct()
            ->pluck('user_id');

        $totalScheduledStudents = DB::table('student_exam_schedules')
            ->where('status', 'scheduled')
            ->whereNotIn('user_id', $takenStudentIds)
            ->distinct('user_id')
            ->count('user_id');

        $totalExaminees = DB::table('student_exam_schedules')
            ->whereIn('status', ['attended', 'missed'])
            ->distinct('user_id')
            ->count('user_id');

        $totalStudentsPassed = DB::table('answer_sheets')
            ->where('status', 'checked')
            ->where('total_score', '>=', self::PASSING_SCORE)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        $totalStudents = DB::table('users')
            ->where('role', 'student')
            ->count();

        return response()->json([
            'scheduled_students' => $totalScheduledStudents,
            'examinees' => $totalExaminees,
            'passed_students' => $totalStudentsPassed,
            'total_students' => $totalStudents,
        ]);
    }

    public function instructor()
    {
        $userId = Auth::id();

        $examIds = DB::table('exam_subjects')
            ->where('user_id', $userId)
            ->distinct()
            ->pluck('exam_id');

        $subjects = DB::table('exam_subjects')
            ->where('user_id', $userId)
            ->distinct('subject_id')
            ->count('subject_id');

        $totalStudents = 0;
        $checkedSheets = 0;
        $passedSheets = 0;

        if ($examIds->isNotEmpty()) {
            $totalStudents = DB::table('answer_sheets')
                ->whereIn('exam_id', $examIds)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id');

            $checkedSheets = DB::table('answer_sheets')
                ->whereIn('exam_id', $examIds)
                ->where('status', 'checked')
                ->whereNotNull('total_score')
                ->count();

            $passedSheets = DB::table('answer_sheets')
                ->whereIn('exam_id', $examIds)
                ->where('status', 'checked')
                ->where('total_score', '>=', self::PASSING_SCORE)
                ->count();
        }

        return response()->json([
            'total_students' => $totalStudents,
            'subjects' => $subjects,
            'students_per_subject' => $subjects > 0 ? round($totalStudents / $subjects, 2) : 0,
            'passing_rate' => $this->asPercent($passedSheets, $checkedSheets),
        ]);
    }

    public function student()
    {
        $userId = Auth::id();

        $examsTaken = DB::table('student_exam_schedules')
            ->where('user_id', $userId)
            ->whereIn('status', ['attended', 'missed'])
            ->distinct('exam_id')
            ->count('exam_id');

        $examsCompleted = DB::table('answer_sheets')
            ->where('user_id', $userId)
            ->where('status', 'checked')
            ->distinct('exam_id')
            ->count('exam_id');

        $takenExamIds = DB::table('student_exam_schedules')
            ->where('user_id', $userId)
            ->distinct()
            ->pluck('exam_id');

        $totalSubjects = 0;
        if ($takenExamIds->isNotEmpty()) {
            $totalSubjects = DB::table('exam_subjects')
                ->whereIn('exam_id', $takenExamIds)
                ->distinct('subject_id')
                ->count('subject_id');
        }

        $checkedSheets = DB::table('answer_sheets')
            ->where('user_id', $userId)
            ->where('status', 'checked')
            ->whereNotNull('total_score')
            ->count();

        $passedSheets = DB::table('answer_sheets')
            ->where('user_id', $userId)
            ->where('status', 'checked')
            ->where('total_score', '>=', self::PASSING_SCORE)
            ->count();

        return response()->json([
            'passing_rate' => $this->asPercent($passedSheets, $checkedSheets),
            'exams_completed' => $examsCompleted,
            'exams_taken' => $examsTaken,
            'total_subjects' => $totalSubjects,
        ]);
    }

    private function asPercent(int $passed, int $total): float
    {
        if ($total <= 0) {
            return 0;
        }

        return round(($passed / $total) * 100, 2);
    }
}
