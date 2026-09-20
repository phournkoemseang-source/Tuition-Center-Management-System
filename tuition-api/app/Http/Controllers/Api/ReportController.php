<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /**
     * Monthly summary: money collected vs outstanding per class, plus per-student lines.
     * GET /api/reports/monthly?year=2026&month=9
     */
    public function monthly(Request $request): JsonResponse
    {
        [$year, $month] = $this->period($request);

        // Payments whose due date falls in the selected month
        $base = Payment::query()
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month);

        $perClass = (clone $base)
            ->join('class_rooms', 'class_rooms.id', '=', 'payments.class_room_id')
            ->groupBy('class_rooms.id', 'class_rooms.name')
            ->selectRaw("
                class_rooms.id,
                class_rooms.name,
                count(*) as invoices,
                sum(payments.amount) as billed_total,
                sum((payments.status = 'paid')::int * payments.amount) as collected_total,
                sum((payments.status <> 'paid')::int * payments.amount) as outstanding_total,
                sum((payments.status = 'overdue')::int) as overdue_count
            ")
            ->orderBy('class_rooms.name')
            ->get();

        $totals = [
            'billed' => (float) (clone $base)->sum('amount'),
            'collected' => (float) (clone $base)->where('status', 'paid')->sum('amount'),
            'outstanding' => (float) (clone $base)->whereIn('status', ['unpaid', 'overdue'])->sum('amount'),
            'invoice_count' => (clone $base)->count(),
            'paid_count' => (clone $base)->where('status', 'paid')->count(),
            'unpaid_count' => (clone $base)->where('status', 'unpaid')->count(),
            'overdue_count' => (clone $base)->where('status', 'overdue')->count(),
        ];

        // Method split for collected money this period
        $byMethod = (clone $base)
            ->where('status', 'paid')
            ->groupBy('method')
            ->selectRaw("coalesce(method, 'unknown') as method, sum(amount) as total, count(*) as count")
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'data' => [
                'period' => ['year' => $year, 'month' => $month],
                'totals' => $totals,
                'per_class' => $perClass,
                'by_method' => $byMethod,
            ],
        ]);
    }

    /**
     * Detailed per-student lines for one month's invoices.
     * GET /api/reports/monthly/students?year=2026&month=9
     */
    public function monthlyStudents(Request $request): JsonResponse
    {
        [$year, $month] = $this->period($request);

        $rows = Payment::query()
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month)
            ->join('students', 'students.id', '=', 'payments.student_id')
            ->join('class_rooms', 'class_rooms.id', '=', 'payments.class_room_id')
            ->orderBy('students.full_name')
            ->orderBy('class_rooms.name')
            ->selectRaw("
                payments.id,
                students.full_name as student,
                class_rooms.name as class,
                payments.amount,
                payments.due_date,
                payments.paid_date,
                payments.status,
                coalesce(payments.method, '-') as method
            ")
            ->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * Attendance summary for one month: totals, per class and per student.
     * GET /api/reports/attendance?year=2026&month=9
     */
    public function attendanceMonthly(Request $request): JsonResponse
    {
        [$year, $month] = $this->period($request);

        $base = Attendance::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        $present = (clone $base)->where('status', 'present')->count();
        $late = (clone $base)->where('status', 'late')->count();
        $absent = (clone $base)->where('status', 'absent')->count();
        $sessions = (int) (clone $base)
            ->selectRaw('count(distinct (class_room_id, date)) as sessions')
            ->value('sessions');

        $marked = $present + $late + $absent;

        return response()->json(['data' => [
            'period' => ['year' => $year, 'month' => $month],
            'totals' => [
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'sessions' => $sessions,
                'rate' => $marked > 0 ? (int) round((($present + $late) / $marked) * 100) : null,
            ],
            'per_class' => (clone $base)
                ->join('class_rooms', 'class_rooms.id', '=', 'attendance.class_room_id')
                ->groupBy('class_rooms.id', 'class_rooms.name')
                ->orderBy('class_rooms.name')
                ->selectRaw("
                    class_rooms.id,
                    class_rooms.name,
                    sum((attendance.status = 'present')::int) as present,
                    sum((attendance.status = 'late')::int) as late,
                    sum((attendance.status = 'absent')::int) as absent,
                    count(distinct (attendance.class_room_id, attendance.date)) as sessions
                ")
                ->get(),
            'per_student' => (clone $base)
                ->join('students', 'students.id', '=', 'attendance.student_id')
                ->join('class_rooms', 'class_rooms.id', '=', 'attendance.class_room_id')
                ->groupBy('students.id', 'students.full_name', 'class_rooms.name')
                ->orderBy('students.full_name')
                ->selectRaw("
                    students.id,
                    students.full_name as student,
                    class_rooms.name as class,
                    sum((attendance.status = 'present')::int) as present,
                    sum((attendance.status = 'late')::int) as late,
                    sum((attendance.status = 'absent')::int) as absent
                ")
                ->get()
                ->map(function ($row) {
                    $marked = $row->present + $row->late + $row->absent;
                    $row->rate = $marked > 0 ? (int) round((($row->present + $row->late) / $marked) * 100) : null;

                    return $row;
                }),
        ]]);
    }

    /**
     * CSV download of the per-student detail (opens directly in Excel).
     * GET /api/reports/monthly/export?year=2026&month=9
     */
    public function exportCsv(Request $request): Response
    {
        [$year, $month] = $this->period($request);

        $rows = Payment::query()
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month)
            ->join('students', 'students.id', '=', 'payments.student_id')
            ->join('class_rooms', 'class_rooms.id', '=', 'payments.class_room_id')
            ->orderBy('students.full_name')
            ->selectRaw("
                students.full_name as student,
                class_rooms.name as class,
                payments.amount,
                payments.due_date,
                payments.paid_date,
                payments.status,
                coalesce(payments.method, '-') as method
            ")
            ->get();

        $filename = "tuition-report-{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT).'.csv';

        $header = ['Student', 'Class', 'Amount (USD)', 'Due date', 'Paid date', 'Status', 'Method'];
        $callback = function () use ($rows, $header) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel shows Khmer/diacritics correctly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->student,
                    $r->class,
                    number_format((float) $r->amount, 2),
                    substr((string) $r->due_date, 0, 10),
                    $r->paid_date ? substr((string) $r->paid_date, 0, 10) : '-',
                    $r->status,
                    $r->method,
                ]);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Validate + normalize ?year & ?month (defaults: current month). */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        return [
            (int) ($data['year'] ?? now()->year),
            (int) ($data['month'] ?? now()->month),
        ];
    }
}
