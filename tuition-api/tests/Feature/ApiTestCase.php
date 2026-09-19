<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $teacherUser;

    protected Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.example',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->teacherUser = User::create([
            'name' => 'Teacher',
            'email' => 'teacher@test.example',
            'password' => 'password',
            'role' => 'teacher',
        ]);

        $this->teacher = Teacher::create([
            'full_name' => 'Teacher',
            'subject' => 'English',
            'user_id' => $this->teacherUser->id,
        ]);
    }

    protected function actingAsAdmin(): static
    {
        $this->actingAs($this->admin, 'sanctum');

        return $this;
    }

    protected function actingAsTeacher(): static
    {
        $this->actingAs($this->teacherUser, 'sanctum');

        return $this;
    }

    protected function makeStudent(array $attrs = []): Student
    {
        return Student::create($attrs + [
            'full_name' => fake()->name(),
            'phone' => '012 345 678',
            'parent_contact' => null,
            'enrolled_date' => now()->toDateString(),
            'status' => 'active',
        ]);
    }

    protected function makeClass(array $attrs = []): ClassRoom
    {
        return ClassRoom::create($attrs + [
            'name' => 'Test Class '.fake()->unique()->numberBetween(1, 9999),
            'teacher_id' => $this->teacher->id,
            'schedule' => 'Mon/Wed 8:00-10:00',
            'fee_amount' => 30,
        ]);
    }

    protected function enroll(Student $student, ClassRoom $class): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id,
            'class_room_id' => $class->id,
            'status' => 'active',
        ]);
    }

    protected function makePayment(Student $student, ClassRoom $class, array $attrs = []): Payment
    {
        return Payment::create($attrs + [
            'student_id' => $student->id,
            'class_room_id' => $class->id,
            'amount' => $class->fee_amount,
            'due_date' => now()->startOfMonth()->toDateString(),
            'status' => 'unpaid',
        ]);
    }
}
