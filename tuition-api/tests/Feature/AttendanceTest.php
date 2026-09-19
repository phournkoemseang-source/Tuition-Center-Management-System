<?php

namespace Tests\Feature;

use App\Models\Attendance;
use Tests\Feature\ApiTestCase;

class AttendanceTest extends ApiTestCase
{
    public function test_teacher_can_save_attendance(): void
    {
        $class = $this->makeClass();
        $s1 = $this->makeStudent();
        $s2 = $this->makeStudent();
        $this->enroll($s1, $class);
        $this->enroll($s2, $class);

        $date = now()->toDateString();

        $this->actingAsTeacher()
            ->postJson("/api/classes/{$class->id}/attendance", [
                'date' => $date,
                'records' => [
                    ['student_id' => $s1->id, 'status' => 'present'],
                    ['student_id' => $s2->id, 'status' => 'absent'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Attendance saved');

        $this->assertDatabaseHas('attendance', [
            'student_id' => $s1->id,
            'class_room_id' => $class->id,
            'date' => $date,
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('attendance', [
            'student_id' => $s2->id,
            'date' => $date,
            'status' => 'absent',
        ]);
    }

    public function test_saving_twice_updates_instead_of_duplicating(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($student, $class);
        $date = now()->toDateString();

        $payload = [
            'date' => $date,
            'records' => [['student_id' => $student->id, 'status' => 'present']],
        ];

        $this->actingAsTeacher()->postJson("/api/classes/{$class->id}/attendance", $payload)->assertOk();
        $this->actingAsTeacher()->postJson("/api/classes/{$class->id}/attendance", [
            'date' => $date,
            'records' => [['student_id' => $student->id, 'status' => 'late']],
        ])->assertOk();

        $this->assertSame(1, Attendance::where('student_id', $student->id)->count());
        $this->assertDatabaseHas('attendance', [
            'student_id' => $student->id,
            'status' => 'late',
        ]);
    }

    public function test_index_returns_existing_records_for_date(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($student, $class);
        $date = now()->toDateString();

        Attendance::create([
            'student_id' => $student->id,
            'class_room_id' => $class->id,
            'date' => $date,
            'status' => 'late',
        ]);

        $this->actingAsTeacher()
            ->getJson("/api/classes/{$class->id}/attendance?date={$date}")
            ->assertOk()
            ->assertJsonPath('data.'.$student->id, 'late');
    }

    public function test_rejects_invalid_status(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $this->actingAsTeacher()
            ->postJson("/api/classes/{$class->id}/attendance", [
                'date' => now()->toDateString(),
                'records' => [['student_id' => $student->id, 'status' => 'maybe']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('records.0.status');
    }

    public function test_requires_authentication(): void
    {
        $class = $this->makeClass();

        $this->postJson("/api/classes/{$class->id}/attendance", [
            'date' => now()->toDateString(),
            'records' => [],
        ])->assertUnauthorized();
    }
}
