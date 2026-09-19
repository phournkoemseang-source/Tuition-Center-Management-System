<?php

namespace Tests\Feature;

use App\Models\Payment;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\ApiTestCase;

class PaymentsTest extends ApiTestCase
{
    public function test_admin_can_list_payments_filtered_by_status(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();

        $this->makePayment($student, $class, ['status' => 'paid', 'paid_date' => now()->toDateString(), 'method' => 'cash']);
        $this->makePayment($student, $class, ['status' => 'unpaid']);
        $this->makePayment($student, $class, ['status' => 'overdue']);

        $this->actingAsAdmin()
            ->getJson('/api/payments?status=unpaid')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'unpaid');
    }

    public function test_list_search_filters_by_student_name(): void
    {
        $class = $this->makeClass();
        $alice = $this->makeStudent(['full_name' => 'Alice Search']);
        $bob = $this->makeStudent(['full_name' => 'Bob Search']);
        $this->makePayment($alice, $class);
        $this->makePayment($bob, $class);

        $this->actingAsAdmin()
            ->getJson('/api/payments?search=alice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.full_name', 'Alice Search');
    }

    public function test_mark_paid_with_cash(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $payment = $this->makePayment($student, $class);

        $this->actingAsAdmin()
            ->patchJson("/api/payments/{$payment->id}/mark-paid", ['method' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.method', 'cash');

        $this->assertNotNull($payment->fresh()->paid_date);
    }

    public function test_mark_paid_khqr_without_reference_is_rejected(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $payment = $this->makePayment($student, $class);

        $this->actingAsAdmin()
            ->patchJson("/api/payments/{$payment->id}/mark-paid", ['method' => 'khqr'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reference');

        $this->assertSame('unpaid', $payment->fresh()->status);
    }

    public function test_mark_paid_khqr_with_reference_saves_reference(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $payment = $this->makePayment($student, $class);

        $this->actingAsAdmin()
            ->patchJson("/api/payments/{$payment->id}/mark-paid", [
                'method' => 'khqr',
                'reference' => 'KHQR-abc123',
            ])
            ->assertOk()
            ->assertJsonPath('data.method', 'khqr')
            ->assertJsonPath('data.reference', 'KHQR-abc123');
    }

    public function test_mark_paid_rejects_unknown_method(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $payment = $this->makePayment($student, $class);

        $this->actingAsAdmin()
            ->patchJson("/api/payments/{$payment->id}/mark-paid", ['method' => 'paypal'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('method');
    }

    public function test_teacher_cannot_mark_paid(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $payment = $this->makePayment($student, $class);

        $this->actingAsTeacher()
            ->patchJson("/api/payments/{$payment->id}/mark-paid", ['method' => 'cash'])
            ->assertForbidden();
    }

    public function test_stats_counts_overdue_correctly(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();

        // Overdue: unpaid + past due date
        $this->makePayment($student, $class, [
            'status' => 'unpaid',
            'due_date' => now()->subDays(10)->toDateString(),
        ]);
        // Fresh unpaid (due in the future — must stay unpaid)
        $this->makePayment($student, $class, [
            'status' => 'unpaid',
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->actingAsAdmin()
            ->getJson('/api/payments/stats')
            ->assertOk()
            ->assertJsonPath('data.unpaid_count', 2);

        // refreshOverdue() should have flipped the past-due one
        $this->assertSame(1, Payment::where('status', 'overdue')->count());
    }

    public function test_password_is_hashed_on_login_check(): void
    {
        $user = \App\Models\User::firstWhere('email', 'admin@test.example');
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertNotSame('password', $user->password);
    }
}
