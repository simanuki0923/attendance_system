<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceListController extends Controller
{
    /**
     * 一般ユーザー用 勤怠一覧（月別）
     *
     * ルート: GET /attendance/list （name: attendance.userList）
     * view : resources/views/list.blade.php
     *
     * クエリ:
     *   ?month=2025-11 のように指定可
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        // ---------------------------------
        // 1) 対象月の決定
        // ---------------------------------
        $rawMonth = $request->query('month');

        if ($rawMonth) {
            try {
                $targetMonth = Carbon::createFromFormat('Y-m', $rawMonth)->startOfMonth();
            } catch (\Throwable $e) {
                $targetMonth = Carbon::today()->startOfMonth();
            }
        } else {
            $targetMonth = Carbon::today()->startOfMonth();
        }

        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth   = $targetMonth->copy()->endOfMonth();

        $currentMonthLabel = $targetMonth->format('Y/m');

        // prev / next 月 URL
        $prevMonth = $targetMonth->copy()->subMonthNoOverflow();
        $nextMonth = $targetMonth->copy()->addMonthNoOverflow();

        $prevMonthUrl = route('attendance.userList', [
            'month' => $prevMonth->format('Y-m'),
        ]);

        $nextMonthUrl = null;
        if ($nextMonth->startOfMonth()->lessThanOrEqualTo(Carbon::today()->startOfMonth())) {
            $nextMonthUrl = route('attendance.userList', [
                'month' => $nextMonth->format('Y-m'),
            ]);
        }

        // ---------------------------------
        // 2) 対象月の勤怠取得
        // ---------------------------------
        $records = Attendance::with(['time', 'total'])
            ->where('user_id', $userId)
            ->whereBetween('work_date', [
                $startOfMonth->toDateString(),
                $endOfMonth->toDateString(),
            ])
            ->orderBy('work_date')
            ->get()
            ->keyBy(fn ($a) => $a->work_date->toDateString());

        // ---------------------------------
        // 3) 月の全日を生成
        // ---------------------------------
        $daysInMonth = [];
        $cursor = $startOfMonth->copy();
        while ($cursor->lessThanOrEqualTo($endOfMonth)) {
            $ymd        = $cursor->toDateString();
            $attendance = $records->get($ymd);

            // ★ 打刻がある日は id ベース、ない日は date ベースで詳細へ飛ばす
            if ($attendance) {
                // 既存レコードがある → /attendance/detail/{id}
                $detailUrl = route('attendance.detail', ['id' => $attendance->id]);
            } else {
                // レコードがない → /attendance/detail/date/{date}
                $detailUrl = route('attendance.detail.byDate', ['date' => $ymd]);
            }

            $daysInMonth[] = [
                'date_label'  => $cursor->format('m/d') . '(' . $cursor->locale('ja')->isoFormat('ddd') . ')',
                'start_label' => $this->formatTime($attendance?->time?->start_time),
                'end_label'   => $this->formatTime($attendance?->time?->end_time),
                'break_label' => $this->formatMinutes($attendance?->total?->break_minutes),
                'total_label' => $this->formatMinutes($attendance?->total?->total_work_minutes),

                // ★ null にせず必ずURLを入れる
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

    /**
     * H:i 形式への変換
     */
    private function formatTime(?string $value): string
    {
        if (empty($value)) return '';

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    /**
     * 分 → H:ii 形式
     */
    private function formatMinutes($minutes): string
    {
        if ($minutes === null || (int) $minutes <= 0) return '';

        $minutes = (int) $minutes;
        $hours   = intdiv($minutes, 60);
        $mins    = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }
}


