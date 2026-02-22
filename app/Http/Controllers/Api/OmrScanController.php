<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnswerKey;
use App\Models\AnswerSheet;
use App\Models\ExamSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class OmrScanController extends Controller
{
    public function check(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'entrance_examiner') {
            return response()->json([
                'message' => 'Only entrance examiners can check answer sheets.',
            ], 403);
        }

        $validated = $request->validate([
            'image' => 'nullable|file|image|mimes:jpeg,jpg,png,bmp,webp|max:10240',
            'images' => 'nullable|array|min:1',
            'images.*' => 'file|image|mimes:jpeg,jpg,png,bmp,webp|max:10240',
        ]);

        $files = [];
        if (!empty($validated['image'])) {
            $files[] = $validated['image'];
        }

        if (!empty($validated['images']) && is_array($validated['images'])) {
            $files = array_merge($files, $validated['images']);
        }

        if (empty($files)) {
            return response()->json([
                'message' => 'Please upload at least one image or a folder of images.',
            ], 422);
        }

        $results = [];
        foreach ($files as $file) {
            $results[] = $this->processImage($file);
        }

        $successCount = collect($results)->where('success', true)->count();

        return response()->json([
            'message' => "Processed {$successCount} out of " . count($results) . " image(s).",
            'processed' => $results,
        ]);
    }

    private function processImage($file): array
    {
        $storedPath = $file->store('omr_uploads', 'public');
        $absolutePath = storage_path('app/public/' . $storedPath);

        $omr = $this->runOmrScript($absolutePath);
        if (!$omr['success']) {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'message' => $omr['message'],
            ];
        }

        $payload = trim((string) ($omr['data']['sheet_id'] ?? ''));
        if ($payload === '') {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'message' => 'QR code was not detected from the image.',
            ];
        }

        $sheet = AnswerSheet::with('exam')->where('qr_payload', $payload)->first();
        if (!$sheet) {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'message' => "No answer sheet matched QR payload {$payload}.",
            ];
        }

        $answerKey = AnswerKey::where('exam_id', $sheet->exam_id)->latest('id')->first();
        if (!$answerKey) {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'sheet_code' => $payload,
                'message' => "No answer key found for exam {$sheet->exam?->Exam_Title}.",
            ];
        }

        $studentAnswers = $this->normalizeAnswers((array) ($omr['data']['answers'] ?? []));
        $correctAnswers = $this->normalizeAnswers((array) ($answerKey->answers ?? []));

        $subjectScores = $this->scoreBySubject((int) $sheet->exam_id, $studentAnswers, $correctAnswers);
        $totalScore = !empty($subjectScores)
            ? array_sum(array_column($subjectScores, 'raw_score'))
            : $this->scoreAllQuestions($studentAnswers, $correctAnswers);

        DB::transaction(function () use ($sheet, $storedPath, $studentAnswers, $subjectScores, $totalScore) {
            $sheet->update([
                'image_path' => $storedPath,
                'scanned_data' => $studentAnswers,
                'total_score' => $totalScore,
                'status' => 'checked',
            ]);

            DB::table('exam_results')->where('answer_sheet_id', $sheet->id)->delete();

            if (!empty($subjectScores)) {
                DB::table('exam_results')->insert(array_map(function ($row) use ($sheet) {
                    return [
                        'answer_sheet_id' => $sheet->id,
                        'subject_id' => $row['subject_id'],
                        'raw_score' => $row['raw_score'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $subjectScores));
            }
        });

        return [
            'success' => true,
            'file' => $file->getClientOriginalName(),
            'sheet_code' => $payload,
            'exam_title' => $sheet->exam?->Exam_Title,
            'student_id' => $sheet->user_id,
            'score' => $totalScore,
            'debug_image' => $omr['data']['debug'] ?? null,
        ];
    }

    private function runOmrScript(string $imagePath): array
    {
        $scriptPath = base_path('python/CheckExam.py');
        $workingDir = base_path('python');
        $commands = [
            ['python', $scriptPath, $imagePath],
            ['py', '-3', $scriptPath, $imagePath],
        ];

        foreach ($commands as $command) {
            $process = new Process($command, $workingDir);
            $process->setTimeout(120);
            $process->run();

            if (!$process->isSuccessful()) {
                continue;
            }

            $output = trim($process->getOutput());
            $decoded = json_decode($output, true);
            if (!is_array($decoded)) {
                continue;
            }

            if (!empty($decoded['error'])) {
                return [
                    'success' => false,
                    'message' => (string) $decoded['error'],
                ];
            }

            return [
                'success' => true,
                'data' => $decoded,
            ];
        }

        return [
            'success' => false,
            'message' => 'OMR processing failed. Please verify Python dependencies are installed.',
        ];
    }

    private function normalizeAnswers(array $answers): array
    {
        $normalized = [];
        foreach ($answers as $question => $answer) {
            $key = (string) $question;
            $normalized[$key] = strtoupper(trim((string) $answer));
        }
        return $normalized;
    }

    private function scoreBySubject(int $examId, array $studentAnswers, array $correctAnswers): array
    {
        $rows = ExamSubject::where('exam_id', $examId)->get();
        $scored = [];

        foreach ($rows as $row) {
            $start = (int) $row->Starting_Number;
            $end = (int) $row->Ending_Number;
            $raw = 0;

            for ($q = $start; $q <= $end; $q++) {
                $key = (string) $q;
                if (!isset($correctAnswers[$key])) {
                    continue;
                }

                if (($studentAnswers[$key] ?? null) === $correctAnswers[$key]) {
                    $raw++;
                }
            }

            $scored[] = [
                'subject_id' => (int) $row->subject_id,
                'raw_score' => $raw,
            ];
        }

        return $scored;
    }

    private function scoreAllQuestions(array $studentAnswers, array $correctAnswers): int
    {
        $score = 0;
        foreach ($correctAnswers as $question => $answer) {
            if (($studentAnswers[$question] ?? null) === $answer) {
                $score++;
            }
        }
        return $score;
    }
}
