<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index()
    {
        return response()->json([
            // Eager load the department relationship
            'data' => Program::with('department')->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'Program_Name' => 'required|string|max:255|unique:programs,Program_Name',
            'department_id' => 'required|exists:departments,id'
        ]);

        $program = Program::create($validated);
        return response()->json($program->load('department'), 201);
    }

    public function update(Request $request, Program $program)
    {
        $validated = $request->validate([
            'Program_Name' => 'required|string|max:255|unique:programs,Program_Name,' . $program->id,
            'department_id' => 'required|exists:departments,id'
        ]);

        $program->update($validated);
        return response()->json($program->load('department'));
    }

    public function destroy(Program $program)
    {
        $program->delete();
        return response()->json(null, 204);
    }
}
