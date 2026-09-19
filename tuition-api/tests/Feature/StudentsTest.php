<?php

namespace Tests\Feature;

use App\Models\Student;
use Tests\Feature\ApiTestCase;

class StudentsTest extends ApiTestCase
{
    public function test_admin_can_list_students(): void
    {
        $this->makeStudent(['full_name' => 'Alice Test']);
        $this->makeStudent(['full_name' => 'Bob Test']);

        $this->actingAsAdmin()
            ->getJson('/api/students')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_filters_by_name_search(): void
    {
        $this->makeStudent(['full_name' => 'Alice Anderson']);
        $this->makeStudent(['full_name' => 'Bob Brown']);

        $this->actingAsAdmin()
            ->getJson('/api/students?search=alice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Alice Anderson');
    }

    public function test_teacher_can_view_students(): void
    {
        $this->makeStudent();

        $this->actingAsTeacher()
            ->getJson('/api/students')
            ->assertOk();
    }

    public function test_admin_can_create_student(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/students', [
                'full_name' => 'New Student',
                'phone' => '098 765 432',
                'parent_contact' => '011 111 222',
            ])
            ->assertCreated()
            ->assertJsonPath('data.full_name', 'New Student');

        $this->assertDatabaseHas('students', [
            'full_name' => 'New Student',
            'status' => 'active',
        ]);
    }

    public function test_create_requires_full_name(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/students', ['phone' => '012 345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('full_name');
    }

    public function test_teacher_cannot_create_student(): void
    {
        $this->actingAsTeacher()
            ->postJson('/api/students', ['full_name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_admin_can_update_student(): void
    {
        $student = $this->makeStudent();

        $this->actingAsAdmin()
            ->putJson("/api/students/{$student->id}", [
                'full_name' => 'Updated Name',
                'phone' => '099 999 999',
            ])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Updated Name');
    }

    public function test_admin_can_deactivate_student(): void
    {
        $student = $this->makeStudent();

        $this->actingAsAdmin()
            ->putJson("/api/students/{$student->id}", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_admin_can_delete_student(): void
    {
        $student = $this->makeStudent();

        $this->actingAsAdmin()
            ->deleteJson("/api/students/{$student->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_guest_cannot_access_students(): void
    {
        $this->getJson('/api/students')->assertUnauthorized();
    }
}
