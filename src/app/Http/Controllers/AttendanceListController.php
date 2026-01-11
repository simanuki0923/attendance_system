<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AttendanceListController extends Controller
{
    public function index(Request $request): View
    {
        $userId = Auth::id();
        if (! $userId) {
            abort(403);
        }

        $rawMonth = (string) $request->query('month', '');

        try {
            $targetMonth = $rawMonth !== ''
                ? Carbon::createFromFormat('Y-m', $rawMonth)->startOfMonth()
                : Carbon::today()->startOfMonth();
        } catch (\Throwable $throwable) {
            $targetMonth = Carbon::today()->startOfMonth();
        }

        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth = $targetMonth->copy()->endOfMonth();

        $recordsByDate = Attendance::with(['time', 'total'])
            ->where('user_id', $userId)
            ->whereDate('work_date', '>=', $startOfMonth->toDateString())
            ->whereDate('work_date', '<=', $endOfMonth->toDateString())
            ->get()
            ->keyBy(function (Attendance $attendance): string {
                return Carbon::parse($attendance->work_date)->toDateString();
            });

        $currentMonthLabel = $targetMonth->format('Y/m');

        $prevMonthUrl = route('attendance.userList', [
            'month' => $targetMonth->copy()->subMonthNoOverflow()->format('Y-m'),
        ]);

        $nextMonthUrl = route('attendance.userList', [
            'month' => $targetMonth->copy()->addMonthNoOverflow()->format('Y-m'),
        ]);

        $daysInMonth = [];
        $cursorDate = $startOfMonth->copy();

        while ($cursorDate->lessThanOrEqualTo($endOfMonth)) {
            $dateKey = $cursorDate->toDateString();
            $attendance = $recordsByDate->get($dateKey);

            $detailUrl = $attendance
                ? route('attendance.detail', ['id' => $attendance->id])
                : '';

            $daysInMonth[] = [
                'date_label'  => $cursorDate->format('m/d') . '(' . $cursorDate->locale('ja')->isoFormat('ddd') . ')',
                'start_label' => $this->formatTime($attendance?->time?->start_time),
                'end_label'   => $this->formatTime($attendance?->time?->end_time),
                'break_label' => $this->formatMinutes($attendance?->total?->break_minutes),
                'total_label' => $this->formatMinutes($attendance?->total?->total_work_minutes),
                'detail_url'  => $detailUrl,
                'is_active'   => $cursorDate->isToday(),
            ];

            $cursorDate->addDay();
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
        } catch (\Throwable $throwable) {
            return (string) $value;
        }
    }

    private function formatMinutes(mixed $minutes): string
    {
        if ($minutes === null || (int) $minutes <= 0) {
            return '';
        }

        $totalMinutes = (int) $minutes;
        $hours = intdiv($totalMinutes, 60);
        $remainingMinutes = $totalMinutes % 60;

        return sprintf('%d:%02d', $hours, $remainingMinutes);
    }
}
