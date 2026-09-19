<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = (string) $request->query('search', '');

        $students = Student::query()
            ->when($search !== '', fn ($q) => $q->where('full_name', 'ilike', "%{$search}%"))
            ->with(['classRooms', 'payments'])
            ->orderBy('full_name')
            ->get();

        return response()->json(['data' => $students]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $student = Student::create($data + ['enrolled_date' => now()->toDateString()]);

        return response()->json(['data' => $student], 201);
    }

    public function show(Student $student): JsonResponse
    {
        $student->load(['classRooms', 'attendance', 'payments.classRoom']);

        return response()->json(['data' => $student]);
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $student->update($this->validated($request, updating: true));

        return response()->json(['data' => $student->fresh()]);
    }

    public function destroy(Student $student): Response
    {
        $student->delete();

        return response()->noContent();
    }

    private function validated(Request $request, bool $updating = false): array
    {
        $rules = [
            'full_name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'parent_contact' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];

        $data = $request->validate($rules);

        if ($updating && isset($data['status']) && $data['status'] === 'inactive') {
            $data['status'] = 'inactive';
        }

        return $data;
    }
}
