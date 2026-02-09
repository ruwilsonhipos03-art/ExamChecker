<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Department::latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'Department_Name' => 'required|string|max:255|unique:departments,Department_Name',
        ]);

        $department = Department::create($validated);
        return response()->json($department, 201);
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'Department_Name' => 'required|string|max:255|unique:departments,Department_Name,' . $department->id,
        ]);

        $department->update($validated);
        return response()->json($department);
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return response()->json(null, 204);
    }
}
