<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    private function currentEmployeeId(): ?int
    {
        return Employee::where('user_id', Auth::id())->value('id');
    }

    private function ownedExamsQuery()
    {
        $userId = Auth::id();
        $employeeId = $this->currentEmployeeId();

        return Exam::query()->where(function ($query) use ($userId, $employeeId) {
            if ($employeeId) {
                // Keep legacy records (created_by was user_id in old code) visible/editable.
                $query->where('created_by', $employeeId)
                    ->orWhere('created_by', $userId);
                return;
            }

            $query->where('created_by', $userId);
        });
    }

    private function validateSubjectRanges(array $rows): void
    {
        foreach ($rows as $index => $row) {
            $start = (int) ($row['Starting_Number'] ?? 0);
            $end = (int) ($row['Ending_Number'] ?? 0);

            if ($end <= $start) {
                throw ValidationException::withMessages([
                    "exam_subjects.$index.Ending_Number" => 'Ending number must be greater than starting number.',
                ]);
            }
        }
    }

    /**
     * Display only exams created by the currently logged-in user.
     */
    public function index()
    {
        return Exam::with(['creator', 'examSubjects.subject'])
            ->whereIn('id', $this->ownedExamsQuery()->select('id'))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Store a new exam linked to the logged-in user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'Exam_Title' => 'required|string|max:255',
            'Exam_Type'  => 'required|string|max:100',
            'exam_subjects' => 'nullable|array|min:1',
            'exam_subjects.*.subject_id' => 'required|distinct|exists:subjects,id',
            'exam_subjects.*.Starting_Number' => 'required|integer|min:1',
            'exam_subjects.*.Ending_Number' => 'required|integer|min:1',
        ]);
        $this->validateSubjectRanges($validated['exam_subjects'] ?? []);

        $employeeId = $this->currentEmployeeId();
        if (!$employeeId) {
            return response()->json([
                'message' => 'No employee profile is linked to your account. Please contact the administrator.',
            ], 422);
        }

        $exam = DB::transaction(function () use ($validated, $employeeId) {
            $exam = Exam::create([
                'Exam_Title' => $validated['Exam_Title'],
                'Exam_Type'  => $validated['Exam_Type'],
                'created_by' => $employeeId,
            ]);

            $subjects = collect($validated['exam_subjects'] ?? [])
                ->map(function ($row) {
                    return [
                        'subject_id' => (int) $row['subject_id'],
                        'Starting_Number' => (int) $row['Starting_Number'],
                        'Ending_Number' => (int) $row['Ending_Number'],
                        'user_id' => Auth::id(),
                    ];
                })
                ->values()
                ->all();

            if (!empty($subjects)) {
                $exam->examSubjects()->createMany($subjects);
            }

            return $exam;
        });

        return response()->json($exam->load(['creator', 'examSubjects.subject']), 201);
    }

    /**
     * Update an exam, but only if it belongs to the logged-in user.
     */
    public function update(Request $request, $id)
    {
        $exam = $this->ownedExamsQuery()->findOrFail($id);

        $validated = $request->validate([
            'Exam_Title' => 'required|string|max:255',
            'Exam_Type'  => 'required|string|max:100',
            'exam_subjects' => 'nullable|array|min:1',
            'exam_subjects.*.subject_id' => 'required|distinct|exists:subjects,id',
            'exam_subjects.*.Starting_Number' => 'required|integer|min:1',
            'exam_subjects.*.Ending_Number' => 'required|integer|min:1',
        ]);
        $this->validateSubjectRanges($validated['exam_subjects'] ?? []);

        DB::transaction(function () use ($exam, $validated) {
            $exam->update([
                'Exam_Title' => $validated['Exam_Title'],
                'Exam_Type' => $validated['Exam_Type'],
            ]);

            if (array_key_exists('exam_subjects', $validated)) {
                $exam->examSubjects()->where('user_id', Auth::id())->delete();

                $subjects = collect($validated['exam_subjects'] ?? [])
                    ->map(function ($row) {
                        return [
                            'subject_id' => (int) $row['subject_id'],
                            'Starting_Number' => (int) $row['Starting_Number'],
                            'Ending_Number' => (int) $row['Ending_Number'],
                            'user_id' => Auth::id(),
                        ];
                    })
                    ->values()
                    ->all();

                if (!empty($subjects)) {
                    $exam->examSubjects()->createMany($subjects);
                }
            }
        });

        return response()->json($exam->fresh()->load(['creator', 'examSubjects.subject']));
    }

    /**
     * Delete an exam, but only if it belongs to the logged-in user.
     */
    public function destroy($id)
    {
        $exam = $this->ownedExamsQuery()->findOrFail($id);
        $exam->delete();
        return response()->json(null, 204);
    }
}
