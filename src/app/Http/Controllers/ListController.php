<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ListController extends Controller
{
    /**
     * 管理者用 勤怠一覧（1日分）
     *
     * ルート: GET /attendance/month  （name: attendance.month）
     * view : resources/views/admin/list.blade.php
     *
     * クエリパラメータ:
     *   ?date=2025-11-18 のように指定があればその日付、
     *   なければ「今日」の勤怠一覧を表示します。
     */
    public function index(Request $request)
    {
        // -----------------------------
        // 1. 対象日付の決定
        // -----------------------------
        $rawDate = $request->query('date');

        if ($rawDate) {
            // 基本は 'Y-m-d' 形式を優先
            try {
                $targetDate = Carbon::createFromFormat('Y-m-d', $rawDate);
            } catch (\Throwable $e) {
                // それ以外の文字列も parse である程度受ける
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

        // 画面上部の表示用
        $currentDateLabel = $targetDate->locale('ja')->isoFormat('YYYY年M月D日(ddd)');
        $currentDateYmd   = $targetDate->format('Y/m/d');

        // 前日 / 翌日の URL（存在しない場合は null にしたければここで制御）
        $prevDate = $targetDate->copy()->subDay();
        $nextDate = $targetDate->copy()->addDay();

        $prevDateUrl = route('attendance.month', [
            'date' => $prevDate->toDateString(),
        ]);

        $nextDateUrl = route('attendance.month', [
            'date' => $nextDate->toDateString(),
        ]);

        // 例：未来日は「翌日」ボタンを無効にしたい場合はこんな感じ
        $today = Carbon::today();
        if ($nextDate->greaterThan($today)) {
            $nextDateUrl = null;
        }

        // -----------------------------
        // 2. 対象日の勤怠データを取得
        // -----------------------------
        $loginUserId = Auth::id();

        $records = Attendance::with(['user', 'time', 'total'])
            ->whereDate('work_date', $targetDate->toDateString())
            ->orderBy('user_id')
            ->get();

        // -----------------------------
        // 3. Blade 用の配列に整形
        // -----------------------------
        $attendances = $records->map(function (Attendance $attendance) use ($loginUserId) {
            $user  = $attendance->user;
            $time  = $attendance->time;
            $total = $attendance->total;

            $nameLabel  = $user?->name ?? '';

            $startLabel = $this->formatTime($time?->start_time);
            $endLabel   = $this->formatTime($time?->end_time);

            $breakLabel = $this->formatMinutes($total?->break_minutes ?? null);
            $totalLabel = $this->formatMinutes($total?->total_work_minutes ?? null);

            // 詳細画面へのリンク（常に有効にする場合）
            $detailUrl = route('attendance.detail', [
                'attendance' => $attendance->id,
            ]);

            return [
                'name_label'  => $nameLabel,
                'start_label' => $startLabel,
                'end_label'   => $endLabel,
                'break_label' => $breakLabel,
                'total_label' => $totalLabel,
                'detail_url'  => $detailUrl,
                // ログイン中のユーザーの行だけ青枠で強調
                'is_active'   => ($attendance->user_id === $loginUserId),
            ];
        });

        // -----------------------------
        // 4. 画面に渡す
        // -----------------------------
        return view('admin.list', [
            'currentDateLabel' => $currentDateLabel,
            'currentDateYmd'   => $currentDateYmd,
            'prevDateUrl'      => $prevDateUrl,
            'nextDateUrl'      => $nextDateUrl,
            'attendances'      => $attendances,
        ]);
    }

    /**
     * 時刻文字列を "H:i" に整形（null / 空なら空文字）
     */
    private function formatTime(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable $e) {
            // パースできない場合はそのまま返す
            return (string) $value;
        }
    }

    /**
     * 分数を "H:MM" に整形（0 / null なら空文字）
     */
    private function formatMinutes($minutes): string
    {
        if ($minutes === null || (int)$minutes <= 0) {
            return '';
        }

        $minutes = (int) $minutes;
        $hours   = intdiv($minutes, 60);
        $mins    = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }
}
