<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClassRoomController extends Controller
{
    public function index(): JsonResponse
    {
        $classes = ClassRoom::query()
            ->with(['teacher', 'students'])
            ->withCount(['students as active_students_count' => fn ($q) => $q->where('enrollments.status', 'active')])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $classes]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'schedule' => ['required', 'string', 'max:120'],
            'fee_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $class = ClassRoom::create($data);

        return response()->json(['data' => $class->load('teacher')], 201);
    }

    public function update(Request $request, ClassRoom $class): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'teacher_id' => ['sometimes', 'integer', 'exists:teachers,id'],
            'schedule' => ['sometimes', 'string', 'max:120'],
            'fee_amount' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $class->update($data);

        return response()->json(['data' => $class->fresh()->load('teacher')]);
    }

    public function destroy(ClassRoom $class): Response
    {
        $class->delete();

        return response()->noContent();
    }
}
