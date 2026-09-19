<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'class_room_id' => ['required', 'integer', 'exists:class_rooms,id'],
        ]);

        $class = ClassRoom::findOrFail($data['class_room_id']);

        $enrollment = DB::transaction(function () use ($data, $class) {
            $enrollment = $class->enrollments()->firstOrCreate([
                'student_id' => $data['student_id'],
            ], [
                'status' => 'active',
            ]);

            if ($enrollment->wasRecentlyCreated) {
                Payment::create([
                    'student_id' => $data['student_id'],
                    'class_room_id' => $class->id,
                    'amount' => $class->fee_amount,
                    'due_date' => now()->startOfMonth()->addMonth()->toDateString(),
                    'status' => 'unpaid',
                ]);
            }

            return $enrollment;
        });

        return response()->json(['data' => $enrollment->load('student', 'classRoom')], 201);
    }

    public function destroy(Student $student, ClassRoom $class): Response
    {
        $class->enrollments()->where('student_id', $student->id)->update(['status' => 'dropped']);

        return response()->noContent();
    }
}
