<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnswerKey;
use App\Models\AnswerSheet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    private const PASSING_SCORE = 75;
    private const ENTRANCE_TYPE_ALIASES = ['entrance', 'entrance exam', 'screening', 'screening exam'];

    private function orgUnitTable(): string
    {
        if ($this->programOrgUnitColumn() === 'department_id' || $this->employeeOrgUnitColumn() === 'department_id') {
            return 'departments';
        }

        return Schema::hasTable('colleges') ? 'colleges' : 'departments';
    }

    private function orgUnitNameColumn(): string
    {
        $table = $this->orgUnitTable();
        if (Schema::hasColumn($table, 'College_Name')) {
            return 'College_Name';
        }

        if (Schema::hasColumn($table, 'Department_Name')) {
            return 'Department_Name';
        }

        return 'College_Name';
    }

    private function employeeOrgUnitColumn(): string
    {
        if (Schema::hasColumn('employees', 'department_id')) {
            return 'department_id';
        }

        if (Schema::hasColumn('employees', 'college_id')) {
            return 'college_id';
        }

        return 'college_id';
    }

    private function programOrgUnitColumn(): string
    {
        if (Schema::hasColumn('programs', 'department_id')) {
            return 'department_id';
        }

        if (Schema::hasColumn('programs', 'college_id')) {
            return 'college_id';
        }

        return 'college_id';
    }

    /**
     * Fetch all users formatted for the Reports Dashboard.
     */
    public function index(Request $request)
    {
        try {
            // Eager load relationships to avoid N+1 performance issues
            $users = User::with(['employee.college', 'employee.office'])
                ->latest()
                ->get();

            // Transform data into a flat structure for the frontend table
            $reportData = $users->map(function ($user) {
                return [
                    'id'              => $user->id,
                    'id_number'       => $user->employee->Employee_Number ?? 'N/A',
                    'full_name'       => $this->formatFullName($user),
                    'email'           => $user->email,
                    'username'        => $user->username,
                    'role'            => $user->role, // instructor, student, college_dean, etc.
                    'college_id'   => $user->employee->college_id ?? null,
                    'College_Name' => $user->employee->college->College_Name ?? 'N/A',
                    'office_name'     => $user->employee->office->Office_Name ?? 'N/A',
                    // Assuming 'status' is active if email is verified, or add your custom logic
                    'status'          => $user->email_verified_at ? 'active' : 'active',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $reportData
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper to format user name.
     */
    private function formatFullName($user)
    {
        $name = "{$user->last_name}, {$user->first_name}";
        if ($user->middle_initial) {
            $name .= " " . $user->middle_initial . ".";
        }
        if ($user->extension_name) {
            $name .= " " . $user->extension_name;
        }
        return $name;
    }

    public function entranceExamineeResults(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['entrance_examiner', 'college_dean', 'instructor'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only entrance examiners, college deans, and instructors can access this report.',
            ], 403);
        }

        $ownedExamIds = $this->ownedExamIdsForUser((int) $user->id, $user);
        if (empty($ownedExamIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $orgUnitTable = $this->orgUnitTable();
        $orgUnitNameColumn = $this->orgUnitNameColumn();
        $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();

        $rows = DB::table('answer_sheets as ans')
            ->join('users as u', 'u.id', '=', 'ans.user_id')
            ->join('exams as e', 'e.id', '=', 'ans.exam_id')
            ->leftJoin('programs as p', 'p.id', '=', 'e.program_id')
            ->leftJoin('employees as emp', 'emp.id', '=', 'e.created_by')
            ->leftJoin($orgUnitTable . ' as d', 'd.id', '=', 'emp.' . $employeeOrgUnitColumn)
            ->leftJoin('exam_results as er', 'er.answer_sheet_id', '=', 'ans.id')
            ->leftJoin('subjects as s', 's.id', '=', 'er.subject_id')
            ->where('u.role', 'student')
            ->where('ans.status', 'checked')
            ->whereIn('ans.exam_id', $ownedExamIds)
            ->groupBy(
                'ans.id',
                'e.Exam_Title',
                'e.Exam_Type',
                'p.Program_Name',
                'd.' . $orgUnitNameColumn,
                'ans.total_score',
                'u.last_name',
                'u.first_name',
                'u.middle_initial',
                'u.extension_name'
            )
            ->selectRaw("
                ans.id as answer_sheet_id,
                e.Exam_Title as exam_name,
                e.Exam_Type as exam_type,
                COALESCE(p.Program_Name, 'N/A') as program_name,
                COALESCE(d.{$orgUnitNameColumn}, 'N/A') as college_name,
                ans.total_score as total,
                u.last_name,
                u.first_name,
                u.middle_initial,
                u.extension_name,
                COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%math%' THEN er.raw_score END), 0) as math,
                COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%english%' THEN er.raw_score END), 0) as english,
                COALESCE(MAX(CASE
                    WHEN LOWER(s.Subject_Name) LIKE '%science%'
                     AND LOWER(s.Subject_Name) NOT LIKE '%social science%'
                     AND LOWER(s.Subject_Name) NOT LIKE '%social%'
                    THEN er.raw_score
                END), 0) as science,
                COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%social science%' OR LOWER(s.Subject_Name) LIKE '%social%' THEN er.raw_score END), 0) as social_science
            ")
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->get()
            ->map(function ($row) {
                $nameParts = [
                    trim((string) $row->last_name),
                    trim((string) $row->first_name),
                ];

                if (!empty($row->middle_initial)) {
                    $nameParts[] = trim((string) $row->middle_initial);
                }

                if (!empty($row->extension_name)) {
                    $nameParts[] = trim((string) $row->extension_name);
                }

                $fullName = implode(', ', $nameParts);

                return [
                    'answer_sheet_id' => (int) $row->answer_sheet_id,
                    'student_full_name' => $fullName,
                    'exam_name' => $row->exam_name,
                    'exam_type' => (string) ($row->exam_type ?? ''),
                    'program_name' => (string) ($row->program_name ?? 'N/A'),
                    'college_name' => (string) ($row->college_name ?? 'N/A'),
                    'math' => (int) $row->math,
                    'english' => (int) $row->english,
                    'science' => (int) $row->science,
                    'social_science' => (int) $row->social_science,
                    'total' => (int) ($row->total ?? 0),
                    'score' => (int) ($row->total ?? 0),
                    'items' => 100,
                ];
            })
            ->values();
        if ($this->hasScannedByColumn() && !$this->hasAnyRole($user->role, ['college_dean'])) {
            $rows = DB::table('answer_sheets as ans')
                ->join('users as u', 'u.id', '=', 'ans.user_id')
                ->join('exams as e', 'e.id', '=', 'ans.exam_id')
                ->leftJoin('programs as p', 'p.id', '=', 'e.program_id')
                ->leftJoin('employees as emp', 'emp.id', '=', 'e.created_by')
                ->leftJoin($orgUnitTable . ' as d', 'd.id', '=', 'emp.' . $employeeOrgUnitColumn)
                ->leftJoin('exam_results as er', 'er.answer_sheet_id', '=', 'ans.id')
                ->leftJoin('subjects as s', 's.id', '=', 'er.subject_id')
                ->where('u.role', 'student')
                ->where('ans.status', 'checked')
                ->where('ans.scanned_by', $user->id)
                ->whereIn('ans.exam_id', $ownedExamIds)
                ->groupBy(
                    'ans.id',
                    'e.Exam_Title',
                    'e.Exam_Type',
                    'p.Program_Name',
                    'd.' . $orgUnitNameColumn,
                    'ans.total_score',
                    'u.last_name',
                    'u.first_name',
                    'u.middle_initial',
                    'u.extension_name'
                )
                ->selectRaw("
                    ans.id as answer_sheet_id,
                    e.Exam_Title as exam_name,
                    e.Exam_Type as exam_type,
                    COALESCE(p.Program_Name, 'N/A') as program_name,
                    COALESCE(d.{$orgUnitNameColumn}, 'N/A') as college_name,
                    ans.total_score as total,
                    u.last_name,
                    u.first_name,
                    u.middle_initial,
                    u.extension_name,
                    COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%math%' THEN er.raw_score END), 0) as math,
                    COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%english%' THEN er.raw_score END), 0) as english,
                    COALESCE(MAX(CASE
                        WHEN LOWER(s.Subject_Name) LIKE '%science%'
                         AND LOWER(s.Subject_Name) NOT LIKE '%social science%'
                         AND LOWER(s.Subject_Name) NOT LIKE '%social%'
                        THEN er.raw_score
                    END), 0) as science,
                    COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%social science%' OR LOWER(s.Subject_Name) LIKE '%social%' THEN er.raw_score END), 0) as social_science
                ")
                ->orderBy('u.last_name')
                ->orderBy('u.first_name')
                ->get()
                ->map(function ($row) {
                    $nameParts = [
                        trim((string) $row->last_name),
                        trim((string) $row->first_name),
                    ];

                    if (!empty($row->middle_initial)) {
                        $nameParts[] = trim((string) $row->middle_initial);
                    }

                    if (!empty($row->extension_name)) {
                        $nameParts[] = trim((string) $row->extension_name);
                    }

                    $fullName = implode(', ', $nameParts);

                    return [
                        'answer_sheet_id' => (int) $row->answer_sheet_id,
                        'student_full_name' => $fullName,
                        'exam_name' => $row->exam_name,
                        'exam_type' => (string) ($row->exam_type ?? ''),
                        'program_name' => (string) ($row->program_name ?? 'N/A'),
                        'college_name' => (string) ($row->college_name ?? 'N/A'),
                        'math' => (int) $row->math,
                        'english' => (int) $row->english,
                        'science' => (int) $row->science,
                        'social_science' => (int) $row->social_science,
                        'total' => (int) ($row->total ?? 0),
                        'score' => (int) ($row->total ?? 0),
                        'items' => 100,
                    ];
                })
                ->values();
        }

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function entranceStudentsWhoTookExams(Request $request)
    {
        $rows = DB::table('student_exam_schedules as ses')
            ->join('users as u', 'u.id', '=', 'ses.user_id')
            ->join('exams as e', 'e.id', '=', 'ses.exam_id')
            ->leftJoin('answer_sheets as ans', function ($join) {
                $join->on('ans.user_id', '=', 'ses.user_id')
                    ->on('ans.exam_id', '=', 'ses.exam_id')
                    ->whereIn('ans.status', ['scanned', 'checked']);
            })
            ->where('u.role', 'student')
            ->where('e.Exam_Type', 'Entrance')
            ->where('ses.status', '!=', 'attended')
            ->whereNull('ans.id')
            ->groupBy(
                'ses.id',
                'u.last_name',
                'u.first_name',
                'u.middle_initial',
                'u.extension_name',
                'e.Exam_Title',
                'ses.status'
            )
            ->selectRaw("
                ses.id as id,
                u.last_name,
                u.first_name,
                u.middle_initial,
                u.extension_name,
                e.Exam_Title as exam_name,
                ses.status as exam_status
            ")
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->get()
            ->map(function ($row) {
                $parts = [
                    trim((string) $row->last_name),
                    trim((string) $row->first_name),
                ];

                if (!empty($row->middle_initial)) {
                    $parts[] = trim((string) $row->middle_initial);
                }

                if (!empty($row->extension_name)) {
                    $parts[] = trim((string) $row->extension_name);
                }

                return [
                    'id' => (int) $row->id,
                    'student_full_name' => implode(', ', $parts),
                    'exam_name' => (string) $row->exam_name,
                    'exam_status' => (string) $row->exam_status,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function entranceExamineeResultDetail(Request $request, int $answerSheetId)
    {
        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['entrance_examiner', 'college_dean', 'instructor'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only entrance examiners, college deans, and instructors can access this report detail.',
            ], 403);
        }

        $ownedExamIds = $this->ownedExamIdsForUser((int) $user->id, $user);

        $sheetQuery = AnswerSheet::query()
            ->with(['exam', 'user'])
            ->where('id', $answerSheetId)
            ->where('status', 'checked')
            ->whereIn('exam_id', $ownedExamIds);

        if ($this->hasScannedByColumn() && !$this->hasAnyRole($user->role, ['college_dean'])) {
            $sheetQuery->where('scanned_by', $user->id);
        }

        $sheet = $sheetQuery->first();

        if (!$sheet) {
            return response()->json([
                'success' => false,
                'message' => 'Checked answer sheet not found.',
            ], 404);
        }

        $answerKey = AnswerKey::query()
            ->where('exam_id', $sheet->exam_id)
            ->latest('id')
            ->first();

        if (!$answerKey) {
            return response()->json([
                'success' => false,
                'message' => 'Answer key not found for this exam.',
            ], 404);
        }

        $studentAnswers = $this->normalizeAnswers((array) ($sheet->scanned_data ?? []));
        $correctAnswers = $this->normalizeAnswers((array) ($answerKey->answers ?? []));

        $items = [];
        for ($question = 1; $question <= 100; $question++) {
            $questionKey = (string) $question;
            $studentAnswer = $studentAnswers[$questionKey] ?? null;
            $correctAnswer = $correctAnswers[$questionKey] ?? null;

            $items[] = [
                'question' => $question,
                'is_correct' => $studentAnswer !== null && $correctAnswer !== null && $studentAnswer === $correctAnswer,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'answer_sheet_id' => (int) $sheet->id,
                'student_full_name' => $sheet->user ? $this->formatFullName($sheet->user) : '',
                'exam_name' => (string) ($sheet->exam?->Exam_Title ?? ''),
                'items' => $items,
            ],
        ]);
    }

    public function studentExamResults(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Only students can access this report.',
            ], 403);
        }

        $rows = DB::table('answer_sheets as ans')
            ->join('exams as e', 'e.id', '=', 'ans.exam_id')
            ->leftJoin('exam_results as er', 'er.answer_sheet_id', '=', 'ans.id')
            ->leftJoin('subjects as s', 's.id', '=', 'er.subject_id')
            ->where('ans.user_id', $user->id)
            ->where('ans.status', 'checked')
            ->groupBy('ans.id', 'e.Exam_Title', 'e.Exam_Type', 'ans.total_score', 'ans.updated_at')
            ->selectRaw("
                ans.id as answer_sheet_id,
                e.Exam_Title as exam_name,
                e.Exam_Type as exam_type,
                ans.total_score as total,
                ans.updated_at as checked_at,
                COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%math%' THEN er.raw_score END), 0) as math,
                COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%english%' THEN er.raw_score END), 0) as english,
                COALESCE(MAX(CASE
                    WHEN LOWER(s.Subject_Name) LIKE '%science%'
                     AND LOWER(s.Subject_Name) NOT LIKE '%social science%'
                     AND LOWER(s.Subject_Name) NOT LIKE '%social%'
                    THEN er.raw_score
                END), 0) as science,
                COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%social science%' OR LOWER(s.Subject_Name) LIKE '%social%' THEN er.raw_score END), 0) as social_science
            ")
            ->orderByDesc('ans.updated_at')
            ->get()
            ->map(function ($row) {
                $total = (int) ($row->total ?? 0);
                $examType = (string) ($row->exam_type ?? '');
                $isEntrance = $this->isEntranceExamType($examType);
                $isPassed = $total >= self::PASSING_SCORE;
                return [
                    'answer_sheet_id' => (int) $row->answer_sheet_id,
                    'exam_name' => (string) $row->exam_name,
                    'exam_type' => $examType,
                    'is_entrance_exam' => $isEntrance,
                    'math' => (int) $row->math,
                    'english' => (int) $row->english,
                    'science' => (int) $row->science,
                    'social_science' => (int) $row->social_science,
                    'total' => $total,
                    'result' => $isPassed ? 'Passed' : 'Failed',
                    'can_recommend_program' => $isPassed && $isEntrance,
                    'checked_at' => $row->checked_at,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    private function normalizeAnswers(array $answers): array
    {
        $normalized = [];

        foreach ($answers as $question => $value) {
            $key = (string) ((int) $question);
            if ($key === '0') {
                continue;
            }

            if (is_array($value)) {
                $value = reset($value);
            }

            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $answer = strtoupper(trim((string) $value));
            if ($answer === '' || $answer === '-' || $answer === '0' || $answer === 'NONE') {
                $normalized[$key] = null;
                continue;
            }

            $normalized[$key] = preg_replace('/[^A-Z0-9]/', '', $answer);
        }

        return $normalized;
    }

    private function isEntranceExamType(?string $examType): bool
    {
        $value = strtolower(trim((string) $examType));
        return in_array($value, self::ENTRANCE_TYPE_ALIASES, true);
    }

    private function hasAnyRole(?string $roles, array $allowedRoles): bool
    {
        if (!$roles) {
            return false;
        }

        $roleList = array_map('trim', explode(',', $roles));
        foreach ($allowedRoles as $role) {
            if (in_array($role, $roleList, true)) {
                return true;
            }
        }

        return false;
    }

    private function ownedExamIdsForUser(int $userId, ?User $user = null): array
    {
        if ($userId <= 0) {
            return [];
        }

        if ($user && $this->hasAnyRole($user->role, ['college_dean'])) {
            $employeeOrgUnitColumn = $this->employeeOrgUnitColumn();
            $programOrgUnitColumn = $this->programOrgUnitColumn();
            $departmentId = (int) DB::table('employees')
                ->where('user_id', $userId)
                ->value($employeeOrgUnitColumn);

            if ($departmentId <= 0) {
                return [];
            }

            return DB::table('exams as e')
                ->leftJoin('employees as emp', 'emp.id', '=', 'e.created_by')
                ->leftJoin('programs as p', 'p.id', '=', 'e.program_id')
                ->where(function ($query) use ($departmentId, $employeeOrgUnitColumn, $programOrgUnitColumn) {
                    $query->where('p.' . $programOrgUnitColumn, $departmentId)
                        ->orWhere('emp.' . $employeeOrgUnitColumn, $departmentId);
                })
                ->distinct()
                ->pluck('e.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        $employeeId = DB::table('employees')
            ->where('user_id', $userId)
            ->value('id');

        return DB::table('exams')
            ->where(function ($query) use ($employeeId, $userId) {
                if ($employeeId) {
                    $query->where('created_by', $employeeId)
                        ->orWhere('created_by', $userId);
                    return;
                }

                $query->where('created_by', $userId);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function hasScannedByColumn(): bool
    {
        try {
            return Schema::hasColumn('answer_sheets', 'scanned_by');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
