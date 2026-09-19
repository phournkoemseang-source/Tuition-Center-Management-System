<?php

namespace Tests\Feature;

use App\Models\Attendance;
use Tests\Feature\ApiTestCase;

class DashboardTest extends ApiTestCase
{
    public function test_dashboard_counts_active_students_and_classes(): void
    {
        $class = $this->makeClass();
        $this->makeStudent(['status' => 'active']);
        $this->makeStudent(['status' => 'active']);
        $this->makeStudent(['status' => 'inactive']);

        $this->actingAsAdmin()
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_students', 2)
            ->assertJsonPath('data.total_classes', 1)
            ->assertJsonPath('data.total_classes', 1);
    }

    public function test_dashboard_attendance_today(): void
    {
        $class = $this->makeClass();
        $s1 = $this->makeStudent();
        $s2 = $this->makeStudent();
        $s3 = $this->makeStudent();
        $this->enroll($s1, $class);
        $this->enroll($s2, $class);
        $this->enroll($s3, $class);

        foreach ([[$s1, 'present'], [$s2, 'late'], [$s3, 'absent']] as [$s, $status]) {
            Attendance::create([
                'student_id' => $s->id,
                'class_room_id' => $class->id,
                'date' => now()->toDateString(),
                'status' => $status,
            ]);
        }

        $this->actingAsAdmin()
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.attendance_today.present', 1)
            ->assertJsonPath('data.attendance_today.late', 1)
            ->assertJsonPath('data.attendance_today.absent', 1)
            ->assertJsonPath('data.attendance_today.rate', 67);
    }

    public function test_dashboard_unpaid_totals_by_class(): void
    {
        $class = $this->makeClass(['fee_amount' => 40]);
        $s1 = $this->makeStudent();
        $s2 = $this->makeStudent();

        $this->makePayment($s1, $class, ['status' => 'unpaid']);
        $this->makePayment($s2, $class, ['status' => 'overdue']);

        $data = $this->actingAsAdmin()
            ->getJson('/api/dashboard')
            ->json('data');

        $this->assertSame(80.0, (float) $data['unpaid_this_month']['total']);
        $this->assertSame(2, $data['unpaid_this_month']['count']);

        $this->assertCount(1, $data['unpaid_by_class']);
        $this->assertSame($class->name, $data['unpaid_by_class'][0]['name']);
        $this->assertSame(2, (int) $data['unpaid_by_class'][0]['unpaid_count']);
    }

    public function test_dashboard_marks_stale_unpaid_as_overdue(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $payment = $this->makePayment($student, $class, [
            'status' => 'unpaid',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->actingAsAdmin()->getJson('/api/dashboard')->assertOk();

        $this->assertSame('overdue', $payment->fresh()->status);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }
}
