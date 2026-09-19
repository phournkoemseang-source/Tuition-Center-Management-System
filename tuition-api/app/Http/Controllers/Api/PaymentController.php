<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', '');
        $search = (string) $request->query('search', '');

        $payments = Payment::query()
            ->with(['student', 'classRoom'])
            ->when($status !== '' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('full_name', 'ilike', "%{$search}%")))
            ->orderBy('due_date')
            ->get();

        return response()->json(['data' => $payments]);
    }

    public function stats(): JsonResponse
    {
        $this->refreshOverdue();

        $stats = [
            'unpaid_total' => (float) Payment::query()->whereIn('status', ['unpaid', 'overdue'])->sum('amount'),
            'unpaid_count' => Payment::query()->whereIn('status', ['unpaid', 'overdue'])->count(),
            'collected_this_month' => (float) Payment::query()->where('status', 'paid')->whereMonth('paid_date', now()->month)->whereYear('paid_date', now()->year)->sum('amount'),
            'collected_count' => Payment::query()->where('status', 'paid')->whereMonth('paid_date', now()->month)->whereYear('paid_date', now()->year)->count(),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Record a payment with a chosen method (cash, khqr, aba, wing, bank)
     * and optional transaction reference (KHQR/bank transfer ID).
     */
    public function markPaid(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
            'method' => ['nullable', 'in:cash,khqr,aba,wing,bank'],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);

        if (($data['method'] ?? null) !== 'cash' && blank($data['reference'] ?? null)) {
            return response()->json([
                'message' => 'A reference (transaction ID / last digits) is required for digital payments.',
                'errors' => ['reference' => ['Please enter the transaction reference for this payment method.']],
            ], 422);
        }

        $payment->update([
            'status' => 'paid',
            'paid_date' => now()->toDateString(),
            'method' => $data['method'] ?? 'cash',
            'reference' => $data['reference'] ?? null,
        ]);

        return response()->json(['data' => $payment->fresh()->load('student', 'classRoom')]);
    }

    private function refreshOverdue(): void
    {
        Payment::query()
            ->where('status', 'unpaid')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }
}
