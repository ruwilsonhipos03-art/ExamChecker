<?php

namespace App\Http\Controllers\Api\EntranceExaminer;

use App\Http\Controllers\Controller;
use App\Models\AnswerKey;
use App\Models\AnswerSheet;
use App\Models\ExamSubject;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class OmrScanController extends Controller
{
    private const SCREENING_TYPE_ALIASES = ['screening', 'screening exam'];
    private const PASSING_SCORE = 75;

    public function check(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$this->hasAnyRole($user->role, ['entrance_examiner', 'college_dean', 'instructor'])) {
            return response()->json([
                'message' => 'Only entrance examiners, college deans, and instructors can check answer sheets.',
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

        $user = Auth::user();
        if (!$user || !$this->canManageExam($user, (int) $sheet->exam_id)) {
            return [
                'success' => false,
                'file' => $file->getClientOriginalName(),
                'sheet_code' => $payload,
                'message' => "You can only check answer sheets for exams you created.",
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

        DB::transaction(function () use ($sheet, $storedPath, $studentAnswers, $subjectScores, $totalScore, $user) {
            $updates = [
                'image_path' => $storedPath,
                'scanned_data' => $studentAnswers,
                'total_score' => $totalScore,
                'status' => 'checked',
            ];
            if ($this->hasScannedByColumn()) {
                $updates['scanned_by'] = $user?->id;
            }

            $sheet->update($updates);

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

        if (
            $this->isScreeningExamType((string) ($sheet->exam?->Exam_Type ?? ''))
            && $totalScore >= self::PASSING_SCORE
            && (int) ($sheet->exam?->program_id ?? 0) > 0
            && (int) ($sheet->user_id ?? 0) > 0
        ) {
            $this->assignStudentProgram((int) $sheet->user_id, (int) $sheet->exam->program_id);
        }

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
        $commands = $this->buildOmrCommands($scriptPath, $imagePath);
        $attemptErrors = [];

        foreach ($commands as $command) {
            $process = new Process($command, $workingDir, [
                'OPENBLAS_NUM_THREADS' => (string) env('OMR_OPENBLAS_THREADS', '1'),
                'OMP_NUM_THREADS' => (string) env('OMR_OMP_THREADS', '1'),
                'MKL_NUM_THREADS' => (string) env('OMR_MKL_THREADS', '1'),
                'NUMEXPR_NUM_THREADS' => (string) env('OMR_NUMEXPR_THREADS', '1'),
            ]);
            $process->setTimeout(180);

            try {
                $process->run();
            } catch (\Throwable $e) {
                $attemptErrors[] = sprintf(
                    '[%s] %s',
                    implode(' ', $command),
                    $e->getMessage()
                );
                continue;
            }

            if (!$process->isSuccessful()) {
                $errorDetail = trim($process->getErrorOutput());
                if (method_exists($process, 'isTimedOut') && $process->isTimedOut()) {
                    $errorDetail = $errorDetail !== '' ? $errorDetail : 'Process timed out while scanning the image.';
                }

                if ($errorDetail === '') {
                    $errorDetail = trim($process->getOutput());
                }

                if ($errorDetail === '') {
                    $errorDetail = 'Process exited unsuccessfully with no additional output.';
                }

                $attemptErrors[] = sprintf(
                    '[%s] %s',
                    implode(' ', $command),
                    $errorDetail
                );
                continue;
            }

            $output = trim($process->getOutput());
            $decoded = json_decode($output, true);
            if (!is_array($decoded)) {
                $stderr = trim($process->getErrorOutput());
                $attemptErrors[] = sprintf(
                    '[%s] Invalid JSON output. stdout: %s%s',
                    implode(' ', $command),
                    $output === '' ? '(empty)' : $output,
                    $stderr !== '' ? ' | stderr: ' . $stderr : ''
                );
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
            'message' => !empty($attemptErrors)
                ? 'OMR processing failed: ' . implode(' || ', $attemptErrors)
                : 'OMR processing failed. Please verify Python dependencies are installed.',
        ];
    }

    private function buildOmrCommands(string $scriptPath, string $imagePath): array
    {
        $commands = [];
        $customBinary = trim((string) env('OMR_PYTHON_BIN', ''));
        if ($customBinary !== '') {
            $commands[] = [$customBinary, $scriptPath, $imagePath];
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $commands[] = ['py', '-3', $scriptPath, $imagePath];
            $commands[] = ['python', $scriptPath, $imagePath];
        } else {
            $commands[] = ['python3', $scriptPath, $imagePath];
            $commands[] = ['python', $scriptPath, $imagePath];
        }

        $unique = [];
        foreach ($commands as $command) {
            $key = implode("\0", $command);
            if (!isset($unique[$key])) {
                $unique[$key] = $command;
            }
        }

        return array_values($unique);
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

    private function canManageExam(User $user, int $examId): bool
    {
        if ($examId <= 0) {
            return false;
        }

        $employeeId = DB::table('employees')
            ->where('user_id', $user->id)
            ->value('id');

        return DB::table('exams')
            ->where('id', $examId)
            ->where(function ($query) use ($employeeId, $user) {
                if ($employeeId) {
                    $query->where('created_by', $employeeId)
                        ->orWhere('created_by', $user->id);
                    return;
                }

                $query->where('created_by', $user->id);
            })
            ->exists();
    }

    private function hasScannedByColumn(): bool
    {
        try {
            return Schema::hasColumn('answer_sheets', 'scanned_by');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isScreeningExamType(string $examType): bool
    {
        $value = strtolower(trim($examType));
        return in_array($value, self::SCREENING_TYPE_ALIASES, true);
    }

    private function assignStudentProgram(int $userId, int $programId): void
    {
        if ($userId <= 0 || $programId <= 0) {
            return;
        }

        $student = Student::query()->where('user_id', $userId)->first();
        if ($student) {
            $student->update(['program_id' => $programId]);
            return;
        }

        Student::query()->create([
            'user_id' => $userId,
            'Student_Number' => $this->generateUniqueStudentNumber($userId),
            'program_id' => $programId,
        ]);
    }

    private function generateUniqueStudentNumber(int $userId): string
    {
        $base = 'STU' . str_pad((string) $userId, 6, '0', STR_PAD_LEFT);
        if (!Student::query()->where('Student_Number', $base)->exists()) {
            return $base;
        }

        do {
            $candidate = $base . '-' . strtoupper(substr((string) bin2hex(random_bytes(3)), 0, 6));
        } while (Student::query()->where('Student_Number', $candidate)->exists());

        return $candidate;
    }
}
