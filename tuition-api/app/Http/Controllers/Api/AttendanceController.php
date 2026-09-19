<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClassRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request, ClassRoom $class): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        $records = Attendance::query()
            ->where('class_room_id', $class->id)
            ->where('date', $date)
            ->pluck('status', 'student_id');

        return response()->json(['data' => $records]);
    }

    public function store(Request $request, ClassRoom $class): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'records' => ['required', 'array'],
            'records.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'records.*.status' => ['required', 'in:present,absent,late'],
        ]);

        DB::transaction(function () use ($data, $class) {
            foreach ($data['records'] as $record) {
                Attendance::updateOrCreate(
                    [
                        'class_room_id' => $class->id,
                        'student_id' => $record['student_id'],
                        'date' => $data['date'],
                    ],
                    ['status' => $record['status']],
                );
            }
        });

        return response()->json(['message' => 'Attendance saved']);
    }
}
