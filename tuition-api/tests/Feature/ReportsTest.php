<?php

namespace Tests\Feature;

use Tests\Feature\ApiTestCase;

class ReportsTest extends ApiTestCase
{
    public function test_monthly_report_totals_are_correct(): void
    {
        $class = $this->makeClass(['fee_amount' => 30]);
        $student = $this->makeStudent();

        $this->makePayment($student, $class, ['status' => 'paid', 'paid_date' => now(), 'method' => 'cash']);
        $this->makePayment($student, $class, ['status' => 'unpaid']);
        $this->makePayment($student, $class, ['status' => 'overdue']);

        $response = $this->actingAsAdmin()
            ->getJson('/api/reports/monthly')
            ->assertOk()
            ->assertJsonPath('data.period.year', now()->year)
            ->assertJsonPath('data.period.month', now()->month);

        $totals = $response->json('data.totals');
        $this->assertSame(90.0, (float) $totals['billed']);
        $this->assertSame(30.0, (float) $totals['collected']);
        $this->assertSame(60.0, (float) $totals['outstanding']);
        $this->assertSame(1, $totals['paid_count']);
        $this->assertSame(1, $totals['overdue_count']);
    }

    public function test_report_excludes_other_months(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->makePayment($student, $class, [
            'due_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'status' => 'paid',
            'paid_date' => now()->subMonth(),
        ]);

        $this->actingAsAdmin()
            ->getJson('/api/reports/monthly')
            ->assertOk()
            ->assertJsonPath('data.totals.invoice_count', 0);
    }

    public function test_per_class_breakdown(): void
    {
        $english = $this->makeClass(['fee_amount' => 30]);
        $math = $this->makeClass(['fee_amount' => 50]);
        $student = $this->makeStudent();

        $this->makePayment($student, $english, ['status' => 'paid', 'paid_date' => now(), 'method' => 'khqr', 'reference' => 'X1']);
        $this->makePayment($student, $math, ['status' => 'unpaid']);

        $data = $this->actingAsAdmin()
            ->getJson('/api/reports/monthly')
            ->json('data');

        $this->assertCount(2, $data['per_class']);

        $byName = collect($data['per_class'])->keyBy('name');
        $this->assertSame(30.0, (float) $byName[$english->name]['collected_total']);
        $this->assertSame(50.0, (float) $byName[$math->name]['outstanding_total']);
    }

    public function test_method_split_only_counts_paid(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();

        $this->makePayment($student, $class, ['status' => 'paid', 'paid_date' => now(), 'method' => 'khqr', 'reference' => 'R1']);
        $this->makePayment($student, $class, ['status' => 'paid', 'paid_date' => now(), 'method' => 'cash']);
        $this->makePayment($student, $class, ['status' => 'unpaid']);

        $methods = $this->actingAsAdmin()
            ->getJson('/api/reports/monthly')
            ->json('data.by_method');

        $this->assertCount(2, $methods);
        $byMethod = collect($methods)->keyBy('method');
        $this->assertSame(30.0, (float) $byMethod['khqr']['total']);
        $this->assertSame(30.0, (float) $byMethod['cash']['total']);
    }

    public function test_monthly_students_lists_each_invoice(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent(['full_name' => 'Detail Student']);
        $this->makePayment($student, $class);

        $this->actingAsAdmin()
            ->getJson('/api/reports/monthly/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student', 'Detail Student')
            ->assertJsonPath('data.0.class', $class->name);
    }

    public function test_csv_export_downloads_file_with_correct_headers(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent(['full_name' => 'CSV Student']);
        $this->makePayment($student, $class, ['status' => 'paid', 'paid_date' => now(), 'method' => 'cash']);

        $response = $this->actingAsAdmin()
            ->getJson('/api/reports/monthly/export')
            ->assertOk();

        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Student,Class,"Amount (USD)"', $content);
        $this->assertStringContainsString('CSV Student', $content);
        $this->assertStringContainsString('paid', $content);
    }

    public function test_export_respects_selected_period(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->makePayment($student, $class, [
            'due_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'status' => 'paid',
            'paid_date' => now()->subMonth(),
        ]);

        $content = $this->actingAsAdmin()
            ->getJson('/api/reports/monthly/export?year='.now()->subMonth()->year.'&month='.now()->subMonth()->month)
            ->streamedContent();

        $this->assertStringContainsString($student->full_name, $content);
    }

    public function test_reports_require_authentication(): void
    {
        $this->getJson('/api/reports/monthly')->assertUnauthorized();
        $this->getJson('/api/reports/monthly/export')->assertUnauthorized();
    }

    public function test_teacher_can_view_reports(): void
    {
        $this->actingAsTeacher()
            ->getJson('/api/reports/monthly')
            ->assertOk();
    }
}
