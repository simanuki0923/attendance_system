<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceListController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            abort(403);
        }

        $rawMonth = (string) $request->query('month', '');

        try {
            $targetMonth = $rawMonth !== ''
                ? Carbon::createFromFormat('Y-m', $rawMonth)->startOfMonth()
                : Carbon::today()->startOfMonth();
        } catch (\Throwable $e) {
            $targetMonth = Carbon::today()->startOfMonth();
        }

        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth   = $targetMonth->copy()->endOfMonth();

        $records = Attendance::with(['time', 'total'])
            ->where('user_id', $userId)
            ->whereDate('work_date', '>=', $startOfMonth->toDateString())
            ->whereDate('work_date', '<=', $endOfMonth->toDateString())
            ->get()
            ->keyBy(function (Attendance $a) {
                return Carbon::parse($a->work_date)->toDateString();
            });

        $currentMonthLabel = $targetMonth->format('Y/m');

        $prevMonthUrl = route('attendance.userList', [
            'month' => $targetMonth->copy()->subMonthNoOverflow()->format('Y-m'),
        ]);

        $nextMonth = $targetMonth->copy()->addMonthNoOverflow()->startOfMonth();
        $nextMonthUrl = $nextMonth->greaterThan(Carbon::today()->startOfMonth())
            ? null
            : route('attendance.userList', ['month' => $nextMonth->format('Y-m')]);

        $daysInMonth = [];
        $cursor = $startOfMonth->copy();

        while ($cursor->lessThanOrEqualTo($endOfMonth)) {
            $ymd        = $cursor->toDateString();
            $attendance = $records->get($ymd);

            $detailUrl = $attendance
                ? route('attendance.detail', ['id' => $attendance->id])
                : '';

            $daysInMonth[] = [
                'date_label'  => $cursor->format('m/d') . '(' . $cursor->locale('ja')->isoFormat('ddd') . ')',
                'start_label' => $this->formatTime($attendance?->time?->start_time),
                'end_label'   => $this->formatTime($attendance?->time?->end_time),
                'break_label' => $this->formatMinutes($attendance?->total?->break_minutes),
                'total_label' => $this->formatMinutes($attendance?->total?->total_work_minutes),
                'detail_url'  => $detailUrl,
                'is_active'   => $cursor->isToday(),
            ];

            $cursor->addDay();
        }

        return view('list', [
            'currentMonthLabel' => $currentMonthLabel,
            'prevMonthUrl'      => $prevMonthUrl,
            'nextMonthUrl'      => $nextMonthUrl,
            'attendances'       => $daysInMonth,
        ]);
    }

    private function formatTime(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function formatMinutes($minutes): string
    {
        if ($minutes === null || (int) $minutes <= 0) {
            return '';
        }

        $minutes = (int) $minutes;
        $hours   = intdiv($minutes, 60);
        $mins    = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }
}
