<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnswerSheet;
use App\Models\Exam;
use App\Models\Recommendation;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AnswerSheetController extends Controller
{
    private const SCREENING_TYPE_ALIASES = ['screening', 'screening exam'];
    private const PASSING_SCORE = 75;
    private const TYPE_STUDENT_CHOICE = 'student_choice';
    private const TYPE_FINAL_PROGRAM = 'final_program';
    private const TYPE_CONTINUE_SCREENING = 'continue_screening';

    public function index()
    {
        $user = Auth::user();
        $userId = Auth::id();

        $query = AnswerSheet::with(['exam.creator.college', 'exam.program']);

        if ($user && $user->role === 'student') {
            return $query
                ->where('user_id', $userId)
                ->latest()
                ->get();
        }

        return $query
            ->where(function ($inner) use ($userId) {
                $inner->where('created_by', $userId)
                    ->orWhere(function ($fallback) use ($userId) {
                        $fallback->whereNull('created_by')
                            ->where('user_id', $userId);
                    });
            })
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'exam_id' => 'required|exists:exams,id',
        ]);

        $sheet = $this->createSheet((int) $data['exam_id']);

        return response()->json($sheet->load('exam'), 201);
    }

    public function update(Request $request, $id)
    {
        $sheet = AnswerSheet::where(function ($query) {
            $query->where('created_by', Auth::id())
                ->orWhere(function ($fallback) {
                    $fallback->whereNull('created_by')
                        ->where('user_id', Auth::id());
                });
        })->findOrFail($id);

        $data = $request->validate([
            'image_path' => 'nullable|string',
            'scanned_data' => 'nullable|array',
            'total_score' => 'nullable|integer',
            'status' => 'nullable|in:generated,scanned,checked',
        ]);

        $sheet->update($data);

        return response()->json($sheet->load('exam'));
    }

    public function destroy($id)
    {
        $sheet = AnswerSheet::where(function ($query) {
            $query->where('created_by', Auth::id())
                ->orWhere(function ($fallback) {
                    $fallback->whereNull('created_by')
                        ->where('user_id', Auth::id());
                });
        })->findOrFail($id);
        $sheet->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function generatePdf(Request $request)
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '1024M');

        $data = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'count' => 'required|integer|min:1|max:200',
        ]);

        $examId = (int) $data['exam_id'];
        $count = (int) $data['count'];

        $created = collect();
        for ($i = 0; $i < $count; $i++) {
            $sheet = $this->createSheet($examId);
            $created->push($sheet->load('exam'));
        }

        $pdfSheets = $created->map(fn ($sheet) => $this->formatSheetForPdf($sheet))->all();
        $exam = Exam::find($examId);

        $pdf = Pdf::setOption([
            'dpi' => 96,
            'defaultFont' => 'Arial',
            'isRemoteEnabled' => false,
        ])->loadView('pdf.bubble_sheet', [
            'sheets' => $pdfSheets,
            'exam' => $exam,
        ]);

        $fileName = 'answer_sheets_generated_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($fileName);
    }

    public function printSingle($id)
    {
        @set_time_limit(180);
        @ini_set('memory_limit', '768M');

        $sheet = AnswerSheet::where(function ($query) {
            $query->where('created_by', Auth::id())
                ->orWhere(function ($fallback) {
                    $fallback->whereNull('created_by')
                        ->where('user_id', Auth::id());
                });
        })->findOrFail($id);
        $sheets = [$this->formatSheetForPdf($sheet)];

        $pdf = Pdf::loadView('pdf.bubble_sheet', [
            'sheets' => $sheets,
            'exam' => $sheet->exam,
        ]);

        $fileName = 'answer_sheet_' . $sheet->id . '.pdf';

        return $pdf->download($fileName);
    }

    public function printSelected(Request $request)
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '1024M');

        $data = $request->validate([
            'sheet_ids' => 'required|array|min:1',
            'sheet_ids.*' => 'integer|distinct',
        ]);

        $ids = collect($data['sheet_ids'])->map(fn ($id) => (int) $id)->values();
        $sheets = AnswerSheet::with('exam')
            ->where(function ($query) {
                $query->where('created_by', Auth::id())
                    ->orWhere(function ($fallback) {
                        $fallback->whereNull('created_by')
                            ->where('user_id', Auth::id());
                    });
            })
            ->whereIn('id', $ids)
            ->get();

        if ($sheets->isEmpty()) {
            return response()->json(['message' => 'No valid sheets selected for printing.'], 422);
        }

        if ($sheets->count() !== $ids->count()) {
            return response()->json(['message' => 'Some selected sheets are invalid or not owned by your account.'], 422);
        }

        $pdfSheets = $sheets->map(fn ($sheet) => $this->formatSheetForPdf($sheet))->all();
        $exam = Exam::find($sheets->first()->exam_id);

        $pdf = Pdf::setOption([
            'dpi' => 96,
            'defaultFont' => 'Arial',
            'isRemoteEnabled' => false,
        ])->loadView('pdf.bubble_sheet', [
            'sheets' => $pdfSheets,
            'exam' => $exam,
        ]);

        $fileName = 'answer_sheets_selected_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($fileName);
    }

    public function scanAndLink(Request $request)
    {
        $data = $request->validate([
            'qr_payload' => 'required|string',
        ]);

        $sheet = AnswerSheet::where('qr_payload', $data['qr_payload'])->first();

        if (!$sheet) {
            return response()->json([
                'message' => 'Answer sheet not found.',
            ], 404);
        }

        $userId = Auth::id();
        $studentRole = Auth::user()?->role;

        if ($studentRole !== 'student') {
            return response()->json([
                'message' => 'Only students can scan and link answer sheets.',
            ], 403);
        }

        $currentOwner = $sheet->user_id ? User::find($sheet->user_id) : null;
        $currentOwnerRole = $currentOwner?->role;

        if ($sheet->user_id && (int) $sheet->user_id !== (int) $userId && $currentOwnerRole === 'student') {
            return response()->json([
                'message' => 'Answer sheet is already linked to another student.',
            ], 409);
        }

        if ((int) $sheet->user_id === (int) $userId) {
            if (!$sheet->scanned_at) {
                $sheet->update(['scanned_at' => now()]);
            }

            return response()->json([
                'message' => 'Answer sheet is already linked to your account.',
                'sheet' => $sheet->load('exam'),
            ]);
        }

        $sheet->loadMissing('exam.creator');
        if ($this->isScreeningExamType($sheet->exam?->Exam_Type)) {
            $screeningGuard = $this->validateScreeningScanOrder($userId, $sheet->exam);
            if (!($screeningGuard['allowed'] ?? false)) {
                return response()->json([
                    'message' => $screeningGuard['message'] ?? 'You cannot take this screening exam yet.',
                ], 422);
            }
        }

        $updates = [
            'user_id' => $userId,
            'status' => $sheet->status ?: 'generated',
            'scanned_at' => now(),
        ];

        if (empty($sheet->created_by) && $sheet->user_id && $currentOwnerRole !== 'student') {
            $updates['created_by'] = (int) $sheet->user_id;
        }

        if (($sheet->status ?? '') === 'generated') {
            $updates['status'] = 'scanned';
        }

        $sheet->update($updates);

        return response()->json([
            'message' => 'Answer sheet linked successfully.',
            'sheet' => $sheet->load('exam'),
        ]);
    }

    private function createSheet(int $examId): AnswerSheet
    {
        $sheet = AnswerSheet::create([
            'qr_payload' => (string) Str::uuid(),
            'exam_id' => $examId,
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'status' => 'generated',
        ]);

        $sheetCode = $this->buildSheetCode($sheet->id, $examId);
        $sheet->update(['qr_payload' => $sheetCode]);

        return $sheet;
    }

    private function buildSheetCode(int $sheetId, int $examId): string
    {
        return $sheetId . '00' . $examId;
    }

    private function formatSheetForPdf(AnswerSheet $sheet): array
    {
        $sheetCode = $sheet->qr_payload ?: $this->buildSheetCode($sheet->id, $sheet->exam_id);

        $qrSvg = QrCode::format('svg')
            ->size(100)
            ->margin(0)
            ->generate($sheetCode);

        return [
            'id' => $sheet->id,
            'sheetCode' => $sheetCode,
            'sheetQrMime' => 'image/svg+xml',
            'sheetQr' => base64_encode($qrSvg),
        ];
    }

    private function isScreeningExamType(?string $examType): bool
    {
        $value = strtolower(trim((string) $examType));
        return in_array($value, self::SCREENING_TYPE_ALIASES, true);
    }

    private function validateScreeningScanOrder(int $userId, ?Exam $exam): array
    {
        $selectedPrograms = Recommendation::query()
            ->join('programs', 'programs.id', '=', 'recommendations.program_id')
            ->where('recommendations.user_id', $userId)
            ->where('recommendations.type', self::TYPE_STUDENT_CHOICE)
            ->orderBy('recommendations.rank')
            ->get([
                'recommendations.program_id',
                'recommendations.rank',
                'programs.Program_Name as program_name',
                'programs.college_id as college_id',
            ])
            ->map(function ($row) {
                return [
                    'program_id' => (int) $row->program_id,
                    'rank' => (int) $row->rank,
                    'program_name' => trim((string) $row->program_name),
                    'college_id' => (int) ($row->college_id ?? 0),
                ];
            })
            ->values()
            ->all();

        if (count($selectedPrograms) < 1 || count($selectedPrograms) > 3) {
            return [
                'allowed' => false,
                'message' => 'Select your screening program(s) first before taking screening exams.',
            ];
        }

        $statuses = $this->screeningStatusesByProgram($userId, $selectedPrograms);
        $finalProgramId = (int) (Recommendation::query()
            ->where('user_id', $userId)
            ->where('type', self::TYPE_FINAL_PROGRAM)
            ->value('program_id') ?? 0);
        $continuedProgramIds = Recommendation::query()
            ->where('user_id', $userId)
            ->where('type', self::TYPE_CONTINUE_SCREENING)
            ->pluck('program_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $targetIndex = $this->findProgramIndexForExam(
            (string) ($exam?->Exam_Title ?? ''),
            (int) ($exam?->creator?->college_id ?? 0),
            $selectedPrograms,
            $statuses
        );
        if ($targetIndex === null) {
            return [
                'allowed' => false,
                'message' => 'This screening exam does not match any of your selected programs.',
            ];
        }
        $target = $selectedPrograms[$targetIndex];
        $targetStatus = $statuses[$targetIndex]['status'] ?? 'no_attempt';
        $targetProgramId = (int) ($target['program_id'] ?? 0);

        if ($finalProgramId > 0 && $targetProgramId !== $finalProgramId) {
            return [
                'allowed' => false,
                'message' => 'You already selected your final program. You cannot take lower-ranked screening exams.',
            ];
        }

        if ($targetStatus === 'passed') {
            return [
                'allowed' => false,
                'message' => "You already passed {$target['program_name']} screening exam.",
            ];
        }

        if ($targetStatus === 'in_progress') {
            return [
                'allowed' => false,
                'message' => "Your {$target['program_name']} screening exam is still pending checking.",
            ];
        }

        for ($i = 0; $i < $targetIndex; $i++) {
            $priorProgram = $selectedPrograms[$i];
            $priorStatus = $statuses[$i]['status'] ?? 'no_attempt';

            if ($priorStatus === 'no_attempt') {
                return [
                    'allowed' => false,
                    'message' => "Take {$priorProgram['program_name']} screening exam first before {$target['program_name']}.",
                ];
            }

            if ($priorStatus === 'in_progress') {
                return [
                    'allowed' => false,
                    'message' => "Wait for the {$priorProgram['program_name']} screening result before taking {$target['program_name']}.",
                ];
            }

            if ($priorStatus === 'passed') {
                $priorProgramId = (int) ($priorProgram['program_id'] ?? 0);
                if (in_array($priorProgramId, $continuedProgramIds, true)) {
                    continue;
                }

                return [
                    'allowed' => false,
                    'message' => "You passed {$priorProgram['program_name']}. Choose whether to continue to the next screening exam or pick it as your final program in Recommendations.",
                ];
            }
        }

        return [
            'allowed' => true,
            'message' => '',
        ];
    }

    private function screeningStatusesByProgram(int $userId, array $programs): array
    {
        $attempts = DB::table('answer_sheets as ans')
            ->join('exams as e', 'e.id', '=', 'ans.exam_id')
            ->leftJoin('employees as emp', 'emp.id', '=', 'e.created_by')
            ->where('ans.user_id', $userId)
            ->whereIn(DB::raw('LOWER(e.Exam_Type)'), self::SCREENING_TYPE_ALIASES)
            ->orderByDesc('ans.updated_at')
            ->select('ans.status', 'ans.total_score', 'e.Exam_Title', 'emp.college_id')
            ->get()
            ->map(fn ($row) => [
                'status' => strtolower(trim((string) ($row->status ?? ''))),
                'total_score' => isset($row->total_score) ? (int) $row->total_score : null,
                'exam_title' => (string) ($row->Exam_Title ?? ''),
                'college_id' => (int) ($row->college_id ?? 0),
            ])
            ->values()
            ->all();

        $deptProgramCounts = collect($programs)
            ->groupBy(fn (array $program) => (int) ($program['college_id'] ?? 0))
            ->map(fn ($rows) => count($rows))
            ->all();

        return collect($programs)->map(function (array $program) use ($attempts, $deptProgramCounts) {
            $programName = trim((string) ($program['program_name'] ?? ''));
            $programDepartmentId = (int) ($program['college_id'] ?? 0);
            $allowDepartmentFallback = $programDepartmentId > 0
                && (($deptProgramCounts[$programDepartmentId] ?? 0) === 1);

            $latest = collect($attempts)->first(function (array $attempt) use ($programName, $programDepartmentId, $allowDepartmentFallback) {
                return $this->examMatchesProgram(
                    (string) ($attempt['exam_title'] ?? ''),
                    $programName,
                    (int) ($attempt['college_id'] ?? 0),
                    $programDepartmentId,
                    $allowDepartmentFallback
                );
            });

            if (!$latest) {
                return ['status' => 'no_attempt'];
            }

            $sheetStatus = strtolower(trim((string) ($latest['status'] ?? '')));
            if ($sheetStatus !== 'checked') {
                return ['status' => 'in_progress'];
            }

            $score = (int) ($latest['total_score'] ?? 0);
            return ['status' => $score >= self::PASSING_SCORE ? 'passed' : 'failed'];
        })->values()->all();
    }

    private function findProgramIndexForExam(string $examTitle, int $examDepartmentId, array $programs, array $statuses): ?int
    {
        $normalizedTitle = strtolower(trim($examTitle));

        $titleMatches = [];
        if ($normalizedTitle !== '') {
            foreach ($programs as $index => $program) {
                $programName = (string) ($program['program_name'] ?? '');
                if ($this->titleMatchesProgramName($normalizedTitle, $programName)) {
                    $titleMatches[] = $index;
                }
            }
        }

        if (!empty($titleMatches)) {
            return $titleMatches[0];
        }

        if ($examDepartmentId > 0) {
            $deptMatches = [];
            foreach ($programs as $index => $program) {
                if ((int) ($program['college_id'] ?? 0) === $examDepartmentId) {
                    $deptMatches[] = $index;
                }
            }

            if (!empty($deptMatches)) {
                if (count($deptMatches) === 1) {
                    return $deptMatches[0];
                }

                // Multiple selected programs in the same department:
                // prefer the first one that has not been attempted yet.
                foreach ($deptMatches as $index) {
                    if (($statuses[$index]['status'] ?? 'no_attempt') === 'no_attempt') {
                        return $index;
                    }
                }

                // Fallback to first failed one, then first in list.
                foreach ($deptMatches as $index) {
                    if (($statuses[$index]['status'] ?? '') === 'failed') {
                        return $index;
                    }
                }

                return $deptMatches[0];
            }
        }

        return null;
    }

    private function examMatchesProgram(
        string $examTitle,
        string $programName,
        int $examDepartmentId,
        int $programDepartmentId,
        bool $allowDepartmentFallback
    ): bool {
        if ($this->titleMatchesProgramName($examTitle, $programName)) {
            return true;
        }

        if ($allowDepartmentFallback && $examDepartmentId > 0 && $programDepartmentId > 0) {
            return $examDepartmentId === $programDepartmentId;
        }

        return false;
    }

    private function titleMatchesProgramName(string $examTitle, string $programName): bool
    {
        $normalizedTitle = $this->normalizeText($examTitle);
        $normalizedProgram = $this->normalizeText($programName);

        if ($normalizedTitle === '' || $normalizedProgram === '') {
            return false;
        }

        if (str_contains($normalizedTitle, $normalizedProgram) || str_contains($normalizedProgram, $normalizedTitle)) {
            return true;
        }

        $tokens = $this->significantTokens($normalizedProgram);
        if (empty($tokens)) {
            return false;
        }

        $matched = 0;
        foreach ($tokens as $token) {
            if (str_contains($normalizedTitle, $token)) {
                $matched++;
            }
        }

        $requiredMatches = count($tokens) >= 3 ? 2 : 1;
        return $matched >= $requiredMatches;
    }

    private function normalizeText(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        return trim($value);
    }

    private function significantTokens(string $value): array
    {
        $stopWords = ['bs', 'ba', 'bsa', 'bsc', 'bachelor', 'in', 'of', 'major', 'program'];
        $parts = array_filter(explode(' ', $value), function ($token) use ($stopWords) {
            return strlen($token) >= 3 && !in_array($token, $stopWords, true);
        });

        return array_values(array_unique($parts));
    }
}
