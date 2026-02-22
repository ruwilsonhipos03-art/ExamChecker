<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnswerSheet;
use App\Models\ProgramRequirement;
use App\Models\Recommendation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentRecommendationController extends Controller
{
    private const ENTRANCE_TYPE_ALIASES = ['entrance', 'entrance exam', 'screening', 'screening exam'];
    private const SCREENING_TYPE_ALIASES = ['screening', 'screening exam'];
    private const PASSING_SCORE = 75;

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Only students can access recommendations.',
            ], 403);
        }

        $answerSheetId = $request->integer('answer_sheet_id');

        $sheetQuery = AnswerSheet::query()
            ->with('exam')
            ->where('user_id', $user->id)
            ->where('status', 'checked')
            ->where('total_score', '>=', self::PASSING_SCORE)
            ->orderByDesc('updated_at');

        if ($answerSheetId) {
            $sheetQuery->where('id', $answerSheetId);
        }

        $sheet = $sheetQuery->first();
        if (!$sheet) {
            return response()->json([
                'success' => true,
                'data' => [
                    'eligible' => false,
                    'message' => 'No passed entrance exam result found yet.',
                    'programs' => [],
                    'selected_program_ids' => [],
                ],
            ]);
        }

        if (!$this->isEntranceExamType($sheet->exam?->Exam_Type)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'eligible' => false,
                    'message' => 'Program recommendation is only available for entrance exams.',
                    'programs' => [],
                    'selected_program_ids' => [],
                ],
            ]);
        }

        $scores = $this->subjectScoresForAnswerSheet((int) $sheet->id);
        $totalScore = (int) ($sheet->total_score ?? 0);

        $requirements = ProgramRequirement::query()
            ->with('program.department')
            ->get();

        $programs = $requirements
            ->map(function (ProgramRequirement $requirement) use ($scores, $totalScore) {
                $importanceTotal =
                    (float) $requirement->math_scale +
                    (float) $requirement->english_scale +
                    (float) $requirement->science_scale +
                    (float) $requirement->social_science_scale;

                $weightedSum =
                    ($scores['math'] * (float) $requirement->math_scale) +
                    ($scores['english'] * (float) $requirement->english_scale) +
                    ($scores['science'] * (float) $requirement->science_scale) +
                    ($scores['social_science'] * (float) $requirement->social_science_scale);

                $weightedScore = $importanceTotal > 0 ? round($weightedSum / $importanceTotal, 2) : 0.0;
                $minimumScore = (int) $requirement->total_score;
                $isQualified = $totalScore >= $minimumScore;

                return [
                    'program_requirement_id' => (int) $requirement->id,
                    'program_id' => (int) $requirement->program_id,
                    'program_name' => (string) ($requirement->program?->Program_Name ?? ''),
                    'department_name' => (string) ($requirement->program?->department?->Department_Name ?? ''),
                    'minimum_total_score' => $minimumScore,
                    'student_total_score' => $totalScore,
                    'is_qualified' => $isQualified,
                    'weighted_score' => $weightedScore,
                    'importance' => [
                        'math' => (float) $requirement->math_scale,
                        'english' => (float) $requirement->english_scale,
                        'science' => (float) $requirement->science_scale,
                        'social_science' => (float) $requirement->social_science_scale,
                    ],
                ];
            })
            ->filter(fn (array $row) => $row['program_id'] > 0 && $row['program_name'] !== '')
            ->sortByDesc(function (array $row) {
                return ($row['is_qualified'] ? 100000 : 0) + $row['weighted_score'];
            })
            ->values();

        $selectedProgramIds = Recommendation::query()
            ->where('user_id', $user->id)
            ->where('type', 'student_choice')
            ->orderBy('rank')
            ->pluck('program_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $selectionState = $this->selectionLockState($user->id, $selectedProgramIds);

        return response()->json([
            'success' => true,
            'data' => [
                'eligible' => true,
                'answer_sheet_id' => (int) $sheet->id,
                'exam_name' => (string) ($sheet->exam?->Exam_Title ?? ''),
                'student_scores' => $scores + ['total' => $totalScore],
                'programs' => $programs,
                'selected_program_ids' => $selectedProgramIds,
                'selection_locked' => $selectionState['locked'],
                'can_repick' => $selectionState['can_repick'],
                'lock_reason' => $selectionState['reason'],
                'screening_statuses' => $selectionState['statuses'],
            ],
        ]);
    }

    public function saveSelection(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Only students can save recommendations.',
            ], 403);
        }

        $validated = $request->validate([
            'answer_sheet_id' => 'nullable|integer|exists:answer_sheets,id',
            'program_ids' => 'required|array|size:3',
            'program_ids.*' => 'required|integer|distinct|exists:programs,id',
        ]);

        $existingSelections = Recommendation::query()
            ->where('user_id', $user->id)
            ->where('type', 'student_choice')
            ->orderBy('rank')
            ->pluck('program_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $lockState = $this->selectionLockState($user->id, $existingSelections);
        if (count($existingSelections) === 3 && $lockState['locked']) {
            return response()->json([
                'success' => false,
                'message' => $lockState['reason'],
            ], 422);
        }

        $request->merge(['answer_sheet_id' => $validated['answer_sheet_id'] ?? null]);
        $recommendationData = $this->index($request)->getData(true);

        $payload = $recommendationData['data'] ?? [];
        if (!($payload['eligible'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $payload['message'] ?? 'No eligible entrance exam result found.',
            ], 422);
        }

        $eligibleProgramIds = collect($payload['programs'] ?? [])
            ->filter(fn ($row) => !empty($row['is_qualified']))
            ->pluck('program_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selected = collect($validated['program_ids'])->map(fn ($id) => (int) $id)->all();
        $hasInvalidSelection = collect($selected)->contains(fn ($id) => !in_array($id, $eligibleProgramIds, true));
        if ($hasInvalidSelection) {
            return response()->json([
                'success' => false,
                'message' => 'Selections must come from qualified program recommendations.',
            ], 422);
        }

        Recommendation::query()
            ->where('user_id', $user->id)
            ->where('type', 'student_choice')
            ->delete();

        foreach ($selected as $index => $programId) {
            Recommendation::create([
                'user_id' => $user->id,
                'program_id' => $programId,
                'rank' => $index + 1,
                'type' => 'student_choice',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => count($existingSelections) === 3
                ? 'Top 3 program choices updated after failing all selected screening exams.'
                : 'Top 3 program choices saved.',
        ]);
    }

    private function subjectScoresForAnswerSheet(int $answerSheetId): array
    {
        $row = DB::table('exam_results as er')
            ->join('subjects as s', 's.id', '=', 'er.subject_id')
            ->where('er.answer_sheet_id', $answerSheetId)
            ->selectRaw("
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
            ->first();

        return [
            'math' => (int) ($row->math ?? 0),
            'english' => (int) ($row->english ?? 0),
            'science' => (int) ($row->science ?? 0),
            'social_science' => (int) ($row->social_science ?? 0),
        ];
    }

    private function isEntranceExamType(?string $examType): bool
    {
        $value = strtolower(trim((string) $examType));
        return in_array($value, self::ENTRANCE_TYPE_ALIASES, true);
    }

    private function selectionLockState(int $userId, array $selectedProgramIds): array
    {
        $selectedProgramIds = collect($selectedProgramIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if (count($selectedProgramIds) < 3) {
            return [
                'locked' => false,
                'can_repick' => true,
                'reason' => '',
                'statuses' => [],
            ];
        }

        $programs = \App\Models\Program::query()
            ->whereIn('id', $selectedProgramIds)
            ->get(['id', 'Program_Name'])
            ->keyBy('id');

        $statuses = [];
        $allFailed = true;

        foreach ($selectedProgramIds as $programId) {
            $program = $programs->get($programId);
            $programName = trim((string) ($program?->Program_Name ?? ''));

            $latest = DB::table('answer_sheets as ans')
                ->join('exams as e', 'e.id', '=', 'ans.exam_id')
                ->where('ans.user_id', $userId)
                ->where('ans.status', 'checked')
                ->whereIn(DB::raw('LOWER(e.Exam_Type)'), self::SCREENING_TYPE_ALIASES)
                ->when($programName !== '', function ($query) use ($programName) {
                    $query->whereRaw('LOWER(e.Exam_Title) LIKE ?', ['%' . strtolower($programName) . '%']);
                })
                ->orderByDesc('ans.updated_at')
                ->select('ans.total_score', 'ans.updated_at', 'e.Exam_Title')
                ->first();

            if (!$latest) {
                $statuses[] = [
                    'program_id' => $programId,
                    'program_name' => $programName,
                    'status' => 'no_attempt',
                    'exam_title' => null,
                    'total_score' => null,
                ];
                $allFailed = false;
                continue;
            }

            $score = (int) ($latest->total_score ?? 0);
            $failed = $score < self::PASSING_SCORE;

            $statuses[] = [
                'program_id' => $programId,
                'program_name' => $programName,
                'status' => $failed ? 'failed' : 'passed',
                'exam_title' => (string) ($latest->Exam_Title ?? ''),
                'total_score' => $score,
            ];

            if (!$failed) {
                $allFailed = false;
            }
        }

        if ($allFailed) {
            return [
                'locked' => false,
                'can_repick' => true,
                'reason' => 'You failed all 3 selected screening exams. You may pick a new top 3.',
                'statuses' => $statuses,
            ];
        }

        return [
            'locked' => true,
            'can_repick' => false,
            'reason' => 'You already selected your top 3 programs. Re-picking is allowed only if you fail all 3 selected screening exams.',
            'statuses' => $statuses,
        ];
    }
}
