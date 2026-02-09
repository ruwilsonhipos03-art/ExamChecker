<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Fetch all users formatted for the Reports Dashboard.
     */
    public function index(Request $request)
    {
        try {
            // Eager load relationships to avoid N+1 performance issues
            $users = User::with(['employee.department', 'employee.office'])
                ->latest()
                ->get();

            // Transform data into a flat structure for the frontend table
            $reportData = $users->map(function ($user) {
                return [
                    'id'              => $user->id,
                    'id_number'       => $user->employee->Employee_Number ?? 'N/A',
                    'full_name'       => $this->formatFullName($user),
                    'email'           => $user->email,
                    'username'        => $user->username,
                    'role'            => $user->role, // instructor, student, dept_head, etc.
                    'department_id'   => $user->employee->department_id ?? null,
                    'department_name' => $user->employee->department->Department_Name ?? 'N/A',
                    'office_name'     => $user->employee->office->Office_Name ?? 'N/A',
                    // Assuming 'status' is active if email is verified, or add your custom logic
                    'status'          => $user->email_verified_at ? 'active' : 'active',
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $reportData
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper to format user name.
     */
    private function formatFullName($user)
    {
        $name = "{$user->last_name}, {$user->first_name}";
        if ($user->middle_initial) {
            $name .= " " . $user->middle_initial . ".";
        }
        if ($user->extension_name) {
            $name .= " " . $user->extension_name;
        }
        return $name;
    }
}
