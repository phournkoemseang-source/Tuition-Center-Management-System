<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use Tests\Feature\ApiTestCase;

class ClassesTest extends ApiTestCase
{
    public function test_admin_can_list_classes_with_counts(): void
    {
        $class = $this->makeClass(['name' => 'English Basic']);
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $this->actingAsAdmin()
            ->getJson('/api/classes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'English Basic')
            ->assertJsonPath('data.0.active_students_count', 1);
    }

    public function test_active_count_excludes_dropped_enrollments(): void
    {
        $class = $this->makeClass();
        $active = $this->makeStudent();
        $dropped = $this->makeStudent();
        $this->enroll($active, $class);
        $this->enroll($dropped, $class)->update(['status' => 'dropped']);

        $this->actingAsAdmin()
            ->getJson('/api/classes')
            ->assertOk()
            ->assertJsonPath('data.0.active_students_count', 1);
    }

    public function test_admin_can_create_class(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/classes', [
                'name' => 'Computer Basics',
                'teacher_id' => $this->teacher->id,
                'schedule' => 'Sat/Sun 9:00-11:00',
                'fee_amount' => 40,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Computer Basics');
    }

    public function test_create_validates_teacher_exists(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/classes', [
                'name' => 'Bad Class',
                'teacher_id' => 99999,
                'schedule' => 'Mon 8:00',
                'fee_amount' => 30,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_id');
    }

    public function test_teacher_cannot_create_class(): void
    {
        $this->actingAsTeacher()
            ->postJson('/api/classes', [
                'name' => 'Nope',
                'teacher_id' => $this->teacher->id,
                'schedule' => 'Mon 8:00',
                'fee_amount' => 30,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_update_class(): void
    {
        $class = $this->makeClass();

        $this->actingAsAdmin()
            ->putJson("/api/classes/{$class->id}", ['fee_amount' => 55])
            ->assertOk()
            ->assertJsonPath('data.fee_amount', '55.00');
    }

    public function test_admin_can_delete_class(): void
    {
        $class = $this->makeClass();

        $this->actingAsAdmin()
            ->deleteJson("/api/classes/{$class->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('class_rooms', ['id' => $class->id]);
    }

    public function test_teacher_can_view_classes(): void
    {
        $this->makeClass();

        $this->actingAsTeacher()
            ->getJson('/api/classes')
            ->assertOk();
    }
}
