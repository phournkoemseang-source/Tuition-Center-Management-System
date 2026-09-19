<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = now()->toDateString();

        // Refresh overdue statuses so numbers are always current
        Payment::query()->where('status', 'unpaid')->where('due_date', '<', $today)->update(['status' => 'overdue']);

        $activeStudents = Student::query()->where('status', 'active')->count();

        $attendanceToday = Attendance::query()->where('date', $today)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $presentToday = (int) ($attendanceToday['present'] ?? 0) + (int) ($attendanceToday['late'] ?? 0);
        $markedToday = $attendanceToday->sum();
        $attendanceRate = $markedToday > 0 ? (int) round($presentToday / $markedToday * 100) : null;

        $unpaidByClass = Payment::query()
            ->join('class_rooms', 'class_rooms.id', '=', 'payments.class_room_id')
            ->whereIn('payments.status', ['unpaid', 'overdue'])
            ->groupBy('class_rooms.id', 'class_rooms.name')
            ->selectRaw('class_rooms.id, class_rooms.name, count(*) as unpaid_count, sum(payments.amount) as unpaid_total')
            ->orderByDesc('unpaid_total')
            ->get();

        $weeklyAttendance = Attendance::query()
            ->where('date', '>=', now()->subDays(6)->toDateString())
            ->selectRaw("to_char(date, 'Dy') as day, date, sum((status = 'present')::int + (status = 'late')::int) as present_count, count(*) as total")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => [
                'total_students' => $activeStudents,
                'total_classes' => ClassRoom::count(),
                'unpaid_this_month' => [
                    'total' => (float) Payment::query()->whereIn('status', ['unpaid', 'overdue'])->sum('amount'),
                    'count' => Payment::query()->whereIn('status', ['unpaid', 'overdue'])->count(),
                ],
                'collected_this_month' => (float) Payment::query()->where('status', 'paid')->whereMonth('paid_date', now()->month)->whereYear('paid_date', now()->year)->sum('amount'),
                'attendance_today' => [
                    'present' => (int) ($attendanceToday['present'] ?? 0),
                    'late' => (int) ($attendanceToday['late'] ?? 0),
                    'absent' => (int) ($attendanceToday['absent'] ?? 0),
                    'rate' => $attendanceRate,
                ],
                'unpaid_by_class' => $unpaidByClass,
                'weekly_attendance' => $weeklyAttendance,
            ],
        ]);
    }
}
