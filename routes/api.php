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

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/logout', [AuthController::class, 'logout']);

    // General Resources
    Route::apiResource('exams', \App\Http\Controllers\Api\ExamController::class);

    // Admin Group: Controllers are only resolved when these routes are hit
    Route::prefix('admin')->group(function () {
        Route::get('reports/all-users', [\App\Http\Controllers\Api\ReportController::class, 'index']);

        Route::apiResource('subjects', \App\Http\Controllers\Api\SubjectController::class);
        Route::apiResource('offices', \App\Http\Controllers\Api\OfficeController::class);
        Route::apiResource('departments', \App\Http\Controllers\Api\DepartmentController::class);
        Route::apiResource('exam-schedules', \App\Http\Controllers\Api\ExamScheduleController::class);
        Route::apiResource('programs', \App\Http\Controllers\Api\ProgramController::class);
        Route::apiResource('employees', \App\Http\Controllers\Api\EmployeeController::class);
    });

    // Department Head Group
    Route::prefix('dept_head')->group(function () {
        // Route::get('reports/examinee-results', [\App\Http\Controllers\Api\ReportController::class, 'examineeResults']);
    });

    // Entrance Group
    Route::prefix('entrance')->group(function () {
        // Define routes here
    });

    // Instructor Group
    Route::prefix('instructor')->group(function () {
        // Route::get('reports/examinee-results', [\App\Http\Controllers\Api\ReportController::class, 'examineeResults']);
    });

    // Student Group
    Route::prefix('student')->group(function () {
        // Route::get('reports/examinee-results', [\App\Http\Controllers\Api\ReportController::class, 'examineeResults']);
    });
});