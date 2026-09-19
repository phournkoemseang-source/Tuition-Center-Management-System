<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use Tests\Feature\ApiTestCase;

class EnrollmentTest extends ApiTestCase
{
    public function test_admin_can_enroll_student_and_invoice_is_created(): void
    {
        $class = $this->makeClass(['fee_amount' => 45]);
        $student = $this->makeStudent();

        $this->actingAsAdmin()
            ->postJson('/api/enrollments', [
                'student_id' => $student->id,
                'class_room_id' => $class->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'class_room_id' => $class->id,
            'status' => 'active',
        ]);

        // Enrollment must auto-create next month's invoice
        $this->assertDatabaseHas('payments', [
            'student_id' => $student->id,
            'class_room_id' => $class->id,
            'amount' => '45.00',
            'status' => 'unpaid',
        ]);
    }

    public function test_enrolling_twice_does_not_duplicate_or_double_invoice(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();

        $this->actingAsAdmin()->postJson('/api/enrollments', [
            'student_id' => $student->id,
            'class_room_id' => $class->id,
        ])->assertCreated();

        $this->actingAsAdmin()->postJson('/api/enrollments', [
            'student_id' => $student->id,
            'class_room_id' => $class->id,
        ])->assertCreated();

        $this->assertSame(1, Enrollment::where('student_id', $student->id)->count());
        $this->assertSame(1, Payment::where('student_id', $student->id)->count());
    }

    public function test_admin_can_drop_student_from_class(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $this->actingAsAdmin()
            ->deleteJson("/api/students/{$student->id}/classes/{$class->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'class_room_id' => $class->id,
            'status' => 'dropped',
        ]);
    }

    public function test_teacher_cannot_enroll_students(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();

        $this->actingAsTeacher()
            ->postJson('/api/enrollments', [
                'student_id' => $student->id,
                'class_room_id' => $class->id,
            ])
            ->assertForbidden();
    }

    public function test_enrollment_validates_ids(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/enrollments', [
                'student_id' => 99999,
                'class_room_id' => 99999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id', 'class_room_id']);
    }
}
