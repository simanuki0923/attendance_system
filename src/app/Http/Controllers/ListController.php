<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ListController extends Controller
{
    public function index(Request $request): View
    {
        $rawDate = $request->query('date');

        if ($rawDate) {
            try {
                $targetDate = Carbon::createFromFormat('Y-m-d', (string) $rawDate);
            } catch (\Throwable $throwable) {
                try {
                    $targetDate = Carbon::parse((string) $rawDate);
                } catch (\Throwable $throwable2) {
                    $targetDate = Carbon::today();
                }
            }
        } else {
            $targetDate = Carbon::today();
        }

        $targetDate = $targetDate->startOfDay();

        $currentDateLabel = $targetDate->locale('ja')->isoFormat('YYYY年M月D日(ddd)');
        $currentDateYmd = $targetDate->format('Y/m/d');

        $prevDate = $targetDate->copy()->subDay();
        $nextDate = $targetDate->copy()->addDay();

        $prevDateUrl = route('admin.attendance.list', [
            'date' => $prevDate->toDateString(),
        ]);

        $nextDateUrl = route('admin.attendance.list', [
            'date' => $nextDate->toDateString(),
        ]);

        $today = Carbon::today();
        if ($nextDate->greaterThan($today)) {
            $nextDateUrl = null;
        }

        $loginUserId = Auth::id();

        $records = Attendance::with(['user', 'time', 'total'])
            ->whereDate('work_date', $targetDate->toDateString())
            ->orderBy('user_id')
            ->get();

        $attendances = $records->map(function (Attendance $attendance) use ($loginUserId): array {
            $attendanceUser = $attendance->user;
            $attendanceTime = $attendance->time;
            $attendanceTotal = $attendance->total;

            $nameLabel = $attendanceUser?->name ?? '';
            $startLabel = $this->formatTime($attendanceTime?->start_time);
            $endLabel = $this->formatTime($attendanceTime?->end_time);

            $breakLabel = $this->formatMinutes($attendanceTotal?->break_minutes ?? null);
            $totalLabel = $this->formatMinutes($attendanceTotal?->total_work_minutes ?? null);

            $detailUrl = route('attendance.detail', [
                'id' => $attendance->id,
            ]);

            return [
                'name_label'  => $nameLabel,
                'start_label' => $startLabel,
                'end_label'   => $endLabel,
                'break_label' => $breakLabel,
                'total_label' => $totalLabel,
                'detail_url'  => $detailUrl,
                'is_active'   => ($attendance->user_id === $loginUserId),
            ];
        });

        return view('admin.list', [
            'currentDateLabel' => $currentDateLabel,
            'currentDateYmd'   => $currentDateYmd,
            'prevDateUrl'      => $prevDateUrl,
            'nextDateUrl'      => $nextDateUrl,
            'attendances'      => $attendances,
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
