<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private const PASSING_SCORE = 75;

    /**
     * Fetch all users formatted for the Reports Dashboard.
     */
    public function index(Request $request)
    {
        try {
            // Eager load relationships to avoid N+1 performance issues
            $users = User::with(['employee.department', 'employee.office'])
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
                    'role'            => $user->role, // instructor, student, dept_head, etc.
                    'department_id'   => $user->employee->department_id ?? null,
                    'department_name' => $user->employee->department->Department_Name ?? 'N/A',
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
        $rows = DB::table('answer_sheets as ans')
            ->join('users as u', 'u.id', '=', 'ans.user_id')
            ->join('exams as e', 'e.id', '=', 'ans.exam_id')
            ->leftJoin('exam_results as er', 'er.answer_sheet_id', '=', 'ans.id')
            ->leftJoin('subjects as s', 's.id', '=', 'er.subject_id')
            ->where('u.role', 'student')
            ->where('ans.status', 'checked')
            ->groupBy(
                'ans.id',
                'e.Exam_Title',
                'ans.total_score',
                'u.last_name',
                'u.first_name',
                'u.middle_initial',
                'u.extension_name'
            )
            ->selectRaw("
                ans.id as answer_sheet_id,
                e.Exam_Title as exam_name,
                ans.total_score as total,
                u.last_name,
                u.first_name,
                u.middle_initial,
                u.extension_name,
                COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%math%' THEN er.raw_score END), 0) as math,
                COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%english%' THEN er.raw_score END), 0) as english,
                COALESCE(MAX(CASE WHEN LOWER(s.Subject_Name) LIKE '%science%' THEN er.raw_score END), 0) as science,
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
                    'math' => (int) $row->math,
                    'english' => (int) $row->english,
                    'science' => (int) $row->science,
                    'social_science' => (int) $row->social_science,
                    'total' => (int) ($row->total ?? 0),
                ];
            })
            ->values();

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
                    ->where('ans.status', '=', 'checked');
            })
            ->whereIn('ses.status', ['attended', 'missed'])
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
                ses.status as exam_status,
                COALESCE(MAX(ans.total_score), 0) as total_score
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

                $score = (int) $row->total_score;

                return [
                    'id' => (int) $row->id,
                    'student_full_name' => implode(', ', $parts),
                    'exam_name' => (string) $row->exam_name,
                    'exam_status' => (string) $row->exam_status,
                    'total_score' => $score,
                    'result' => $score >= self::PASSING_SCORE ? 'Passed' : 'Failed',
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }
}
