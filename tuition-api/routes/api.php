<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassRoomController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TeacherController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/login', [AuthController::class, 'login']);

// Authenticated
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Read endpoints (admin + teacher)
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/teachers', [TeacherController::class, 'index']);
    Route::get('/classes', [ClassRoomController::class, 'index']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/stats', [PaymentController::class, 'stats']);

    // Reports (admin + teacher read access)
    Route::get('/reports/monthly', [ReportController::class, 'monthly']);
    Route::get('/reports/monthly/students', [ReportController::class, 'monthlyStudents']);
    Route::get('/reports/monthly/export', [ReportController::class, 'exportCsv']);
    Route::get('/reports/attendance', [ReportController::class, 'attendanceMonthly']);

    // Teachers can take attendance for any class (MVP: single center)
    Route::get('/classes/{class}/attendance', [AttendanceController::class, 'index']);
    Route::post('/classes/{class}/attendance', [AttendanceController::class, 'store']);

    // Admin-only management
    Route::middleware('role:admin')->group(function (): void {
        Route::apiResource('students', StudentController::class)->except(['index']);
        Route::apiResource('teachers', TeacherController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('classes', ClassRoomController::class)->except(['index']);
        Route::post('/enrollments', [EnrollmentController::class, 'store']);
        Route::delete('/students/{student}/classes/{class}', [EnrollmentController::class, 'destroy']);
        Route::patch('/payments/{payment}/mark-paid', [PaymentController::class, 'markPaid']);
    });
});
