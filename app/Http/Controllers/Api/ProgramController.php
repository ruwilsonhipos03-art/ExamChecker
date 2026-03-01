<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProgramController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $canViewPrograms = $this->hasRole($user?->role, 'admin')
            || $this->hasRole($user?->role, 'dept_head')
            || $this->hasRole($user?->role, 'entrance_examiner');

        if (!$canViewPrograms) {
            return response()->json([
                'message' => 'Only admins, college deans, and entrance examiners can view the program list.',
            ], 403);
        }

        $query = Program::with('department')->latest();

        if ($this->hasRole($user?->role, 'dept_head') && !$this->hasRole($user?->role, 'admin')) {
            $departmentId = (int) ($user?->employee?->department_id ?? 0);
            if ($departmentId <= 0) {
                return response()->json([
                    'message' => 'College dean does not have an assigned department.',
                ], 422);
            }

            $query->where('department_id', $departmentId);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$this->hasRole($user?->role, 'admin')) {
            return response()->json([
                'message' => 'Only admins can create programs.',
            ], 403);
        }

        $validated = $request->validate([
            'Program_Name' => 'required|string|max:255|unique:programs,Program_Name',
            'department_id' => 'required|exists:departments,id'
        ]);

        $program = Program::create($validated);
        return response()->json($program->load('department'), 201);
    }

    public function update(Request $request, Program $program)
    {
        $user = Auth::user();
        if (!$this->hasRole($user?->role, 'admin')) {
            return response()->json([
                'message' => 'Only admins can update programs.',
            ], 403);
        }

        $validated = $request->validate([
            'Program_Name' => 'required|string|max:255|unique:programs,Program_Name,' . $program->id,
            'department_id' => 'required|exists:departments,id'
        ]);

        $program->update($validated);
        return response()->json($program->load('department'));
    }

    public function destroy(Program $program)
    {
        $user = Auth::user();
        if (!$this->hasRole($user?->role, 'admin')) {
            return response()->json([
                'message' => 'Only admins can delete programs.',
            ], 403);
        }

        $program->delete();
        return response()->json(null, 204);
    }

    private function hasRole(?string $roles, string $role): bool
    {
        if (!$roles) {
            return false;
        }

        $roleList = array_map('trim', explode(',', $roles));
        return in_array($role, $roleList, true);
    }
}
