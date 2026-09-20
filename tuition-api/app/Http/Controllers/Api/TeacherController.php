<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherController extends Controller
{
    /**
     * List all teachers with the classes they are assigned to (name, schedule, fee, active students).
     * Any authenticated user can read.
     * GET /api/teachers
     */
    public function index(): JsonResponse
    {
        $teachers = Teacher::query()
            ->with('user:id,name,email,role')
            ->with(['classRooms' => function ($q): void {
                $q->select('class_rooms.id', 'class_rooms.name', 'class_rooms.teacher_id', 'class_rooms.schedule', 'class_rooms.fee_amount')
                    ->withCount(['students as active_students_count' => function ($sq): void {
                        $sq->where('enrollments.status', 'active');
                    }])
                    ->orderBy('class_rooms.name');
            }])
            ->withCount(['classRooms as classes_count'])
            ->orderBy('full_name')
            ->get();

        return response()->json(['data' => $teachers]);
    }

    /**
     * Create a teacher together with their login account.
     * POST /api/teachers  (admin only)
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::create([
            'name' => $data['full_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'teacher',
        ]);

        $teacher = Teacher::create([
            'full_name' => $data['full_name'],
            'subject' => $data['subject'],
            'user_id' => $user->id,
        ]);

        return response()->json(['data' => $teacher->load('user')], 201);
    }

    /**
     * Update teacher profile (and optionally reset their password).
     * PUT /api/teachers/{teacher}  (admin only)
     */
    public function update(Request $request, Teacher $teacher): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'subject' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($teacher->user_id)],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if (! $teacher->user) {
            throw ValidationException::withMessages([
                'email' => 'This teacher has no linked login account.',
            ]);
        }

        $teacher->update([
            'full_name' => $data['full_name'] ?? $teacher->full_name,
            'subject' => $data['subject'] ?? $teacher->subject,
        ]);

        $userUpdate = [];
        if (isset($data['full_name'])) {
            $userUpdate['name'] = $data['full_name'];
        }
        if (isset($data['email'])) {
            $userUpdate['email'] = $data['email'];
        }
        if (! empty($data['password'])) {
            $userUpdate['password'] = Hash::make($data['password']);
        }
        if ($userUpdate !== []) {
            $teacher->user->update($userUpdate);
        }

        return response()->json(['data' => $teacher->fresh()->load('user')]);
    }

    /**
     * Delete a teacher and their login account.
     * Blocked while they still teach classes (class_rooms.teacher_id would cascade).
     * DELETE /api/teachers/{teacher}  (admin only)
     */
    public function destroy(Teacher $teacher): Response
    {
        $classesCount = $teacher->classRooms()->count();

        if ($classesCount > 0) {
            throw ValidationException::withMessages([
                'full_name' => "{$teacher->full_name} still teaches {$classesCount} ".
                    ($classesCount === 1 ? 'class' : 'classes').'. Reassign or delete those classes first.',
            ]);
        }

        if ($teacher->user) {
            $teacher->user->delete(); // teachers.user_id cascades on user delete
        } else {
            $teacher->delete();
        }

        return response()->noContent();
    }
}
