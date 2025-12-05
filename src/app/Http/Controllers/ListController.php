<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ListController extends Controller
{
    public function index(Request $request)
    {
        $rawDate = $request->query('date');

        if ($rawDate) {
            try {
                $targetDate = Carbon::createFromFormat('Y-m-d', $rawDate);
            } catch (\Throwable $e) {
                try {
                    $targetDate = Carbon::parse($rawDate);
                } catch (\Throwable $e) {
                    $targetDate = Carbon::today();
                }
            }
        } else {
            $targetDate = Carbon::today();
        }
        $targetDate = $targetDate->startOfDay();

        $currentDateLabel = $targetDate->locale('ja')->isoFormat('YYYY年M月D日(ddd)');
        $currentDateYmd   = $targetDate->format('Y/m/d');

        $prevDate = $targetDate->copy()->subDay();
        $nextDate = $targetDate->copy()->addDay();

        $prevDateUrl = route('attendance.month', [
            'date' => $prevDate->toDateString(),
        ]);

        $nextDateUrl = route('attendance.month', [
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

        $attendances = $records->map(function (Attendance $attendance) use ($loginUserId) {
            $user  = $attendance->user;
            $time  = $attendance->time;
            $total = $attendance->total;

            $nameLabel  = $user?->name ?? '';
            $startLabel = $this->formatTime($time?->start_time);
            $endLabel   = $this->formatTime($time?->end_time);

            $breakLabel = $this->formatMinutes($total?->break_minutes ?? null);
            $totalLabel = $this->formatMinutes($total?->total_work_minutes ?? null);

            // ★ {id} に合わせる
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
        if (empty($value)) return '';

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function formatMinutes($minutes): string
    {
        if ($minutes === null || (int)$minutes <= 0) return '';

        $minutes = (int) $minutes;
        $hours   = intdiv($minutes, 60);
        $mins    = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }
}
