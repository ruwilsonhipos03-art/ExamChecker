<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password/send-code', [AuthController::class, 'sendForgotPasswordCode']);
Route::post('/forgot-password/reset', [AuthController::class, 'resetPasswordWithCode']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email-verification/send', [AuthController::class, 'sendEmailVerificationCode']);
    Route::post('/email-verification/verify', [AuthController::class, 'verifyEmailCode']);
    Route::put('/profile/email', [AuthController::class, 'updateEmail']);

    // General Resources
    Route::apiResource('exams', App\Http\Controllers\Api\ExamController::class);
    Route::get('programs', [App\Http\Controllers\Api\ProgramController::class, 'index']);
    Route::apiResource('answer-sheets', App\Http\Controllers\Api\AnswerSheetController::class);
    Route::post('/answer-sheets/generate', [\App\Http\Controllers\Api\AnswerSheetController::class, 'generatePdf']);
    Route::post('/answer-sheets/print-selected', [\App\Http\Controllers\Api\AnswerSheetController::class, 'printSelected']);
    Route::get('/answer-sheets/{id}/print', [\App\Http\Controllers\Api\AnswerSheetController::class, 'printSingle']);
    // CRUD Routes
    Route::apiResource('answer-keys', App\Http\Controllers\Api\AnswerKeyController::class);

    // PDF Download Route
    Route::get('/answer-keys/{id}/download', [App\Http\Controllers\Api\AnswerKeyController::class, 'downloadPdf']);

    // Admin Group: Controllers are only resolved when these routes are hit
    Route::prefix('admin')->group(function () {
        Route::get('reports/all-users', [\App\Http\Controllers\Api\ReportController::class, 'index']);
        Route::get('dashboard/stats', [\App\Http\Controllers\Api\DashboardStatsController::class, 'admin']);

        Route::apiResource('subjects', App\Http\Controllers\Api\SubjectController::class);
        Route::apiResource('offices', App\Http\Controllers\Api\OfficeController::class);
        Route::apiResource('departments', App\Http\Controllers\Api\DepartmentController::class);
        Route::apiResource('exam-schedules', App\Http\Controllers\Api\ExamScheduleController::class);
        Route::apiResource('programs', App\Http\Controllers\Api\ProgramController::class);
        Route::apiResource('program-requirements', App\Http\Controllers\Api\ProgramRequirementController::class);
        Route::apiResource('employees', App\Http\Controllers\Api\EmployeeController::class);
    });

    // Department Head Group
    Route::prefix('dept_head')->group(function () {
        Route::get('dashboard/stats', [\App\Http\Controllers\Api\DashboardStatsController::class, 'deptHead']);
        Route::get('students', [\App\Http\Controllers\Api\DeptHeadManagementController::class, 'students']);
        Route::get('subjects', [\App\Http\Controllers\Api\DeptHeadManagementController::class, 'subjects']);
        Route::get('instructors', [\App\Http\Controllers\Api\DeptHeadManagementController::class, 'instructors']);

        Route::get('subject-assignments/students', [\App\Http\Controllers\Api\DeptHeadManagementController::class, 'studentAssignments']);
        Route::post('subject-assignments/students', [\App\Http\Controllers\Api\DeptHeadManagementController::class, 'storeStudentAssignment']);
        Route::delete('subject-assignments/students/{id}', [\App\Http\Controllers\Api\DeptHeadManagementController::class, 'destroyStudentAssignment']);

        Route::get('subject-assignments/instructors', [\App\Http\Controllers\Api\DeptHeadManagementController::class, 'instructorAssignments']);
        Route::post('subject-assignments/instructors', [\App\Http\Controllers\Api\DeptHeadManagementController::class, 'storeInstructorAssignment']);
        Route::delete('subject-assignments/instructors/{id}', [\App\Http\Controllers\Api\DeptHeadManagementController::class, 'destroyInstructorAssignment']);
    });

    // Entrance Group
    Route::prefix('entrance')->group(function () {
        Route::get('dashboard/stats', [\App\Http\Controllers\Api\DashboardStatsController::class, 'entrance']);
        Route::get('reports/examinee-results', [\App\Http\Controllers\Api\ReportController::class, 'entranceExamineeResults']);
        Route::get('reports/examinee-results/{answerSheetId}', [\App\Http\Controllers\Api\ReportController::class, 'entranceExamineeResultDetail']);
        Route::get('students/took-exams', [\App\Http\Controllers\Api\ReportController::class, 'entranceStudentsWhoTookExams']);
        Route::post('omr/check', [\App\Http\Controllers\Api\OmrScanController::class, 'check']);
        Route::apiResource('exam-subjects', App\Http\Controllers\Api\ExamSubjectController::class);
        // Define routes here
    });

    // Instructor Group
    Route::prefix('instructor')->group(function () {
        Route::get('dashboard/stats', [\App\Http\Controllers\Api\DashboardStatsController::class, 'instructor']);
        Route::get('students', [\App\Http\Controllers\Api\InstructorManagementController::class, 'students']);
        Route::get('subjects', [\App\Http\Controllers\Api\InstructorManagementController::class, 'subjects']);
        Route::get('subjects/{subjectId}/students', [\App\Http\Controllers\Api\InstructorManagementController::class, 'subjectStudents']);
    });

    // Student Group
    Route::prefix('student')->group(function () {
        Route::get('dashboard/stats', [\App\Http\Controllers\Api\DashboardStatsController::class, 'student']);
        Route::post('answer-sheets/scan', [\App\Http\Controllers\Api\AnswerSheetController::class, 'scanAndLink']);
        Route::get('schedules', [\App\Http\Controllers\Api\StudentScheduleController::class, 'index']);
        Route::get('reports', [\App\Http\Controllers\Api\ReportController::class, 'studentExamResults']);
        Route::get('program-recommendations', [\App\Http\Controllers\Api\StudentRecommendationController::class, 'index']);
        Route::post('program-recommendations/select', [\App\Http\Controllers\Api\StudentRecommendationController::class, 'saveSelection']);
        Route::post('program-recommendations/decision', [\App\Http\Controllers\Api\StudentRecommendationController::class, 'saveScreeningDecision']);
    });
});
