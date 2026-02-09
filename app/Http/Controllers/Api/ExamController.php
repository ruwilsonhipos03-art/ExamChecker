<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamController extends Controller
{
    /**
     * Display only exams created by the currently logged-in user.
     */
    public function index()
    {
        return Exam::with('creator')
            ->where('created_by', Auth::id()) // Only fetch my exams
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
        ]);

        $exam = Exam::create([
            'Exam_Title' => $validated['Exam_Title'],
            'Exam_Type'  => $validated['Exam_Type'],
            'created_by' => Auth::id(), // Links to whoever is logged in
        ]);

        return response()->json($exam->load('creator'), 201);
    }

    /**
     * Update an exam, but only if it belongs to the logged-in user.
     */
    public function update(Request $request, $id)
    {
        // where('created_by', Auth::id()) prevents editing others' work
        $exam = Exam::where('created_by', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'Exam_Title' => 'required|string|max:255',
            'Exam_Type'  => 'required|string|max:100',
        ]);

        $exam->update($validated);
        return response()->json($exam->load('creator'));
    }

    /**
     * Delete an exam, but only if it belongs to the logged-in user.
     */
    public function destroy($id)
    {
        $exam = Exam::where('created_by', Auth::id())->findOrFail($id);
        $exam->delete();
        return response()->json(null, 204);
    }
}
