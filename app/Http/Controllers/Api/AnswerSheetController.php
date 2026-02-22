<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnswerSheet;
use App\Models\Exam;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AnswerSheetController extends Controller
{
    public function index()
    {
        return AnswerSheet::with('exam')
            ->where(function ($query) {
                $query->where('created_by', Auth::id())
                    ->orWhere(function ($fallback) {
                        $fallback->whereNull('created_by')
                            ->where('user_id', Auth::id());
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
}
