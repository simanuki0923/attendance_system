<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DetailtController extends Controller
{
    /**
     * 勤怠詳細画面
     * ルート: /attendance/detail/{attendance}
     * view: resources/views/detail.blade.php
     */
    public function show(Attendance $attendance)
    {
        $loginUser = Auth::user();

        // 自分以外の勤怠が見えないようにガード（必要なければ外してOK）
        if ($attendance->user_id !== $loginUser->id) {
            abort(404);
        }

        // ▼ detail.blade.php が想定しているラベル群を組み立てる想定
        $employeeName = $loginUser->name;

        $workDate = Carbon::parse($attendance->work_date);
        $dateYearLabel = $workDate->format('Y年');
        $dateDayLabel  = $workDate->format('n月j日');

        // 出勤・退勤時刻（attendance_times.start_time / end_time）
        $time = $attendance->time; // Attendance::with('time') がある前提

        $workStartLabel = $this->formatTime($time?->start_time);
        $workEndLabel   = $this->formatTime($time?->end_time);

        // DB上は休憩開始・終了のカラムがない設計なので、ここでは空文字で渡す
        $break1StartLabel = '';
        $break1EndLabel   = '';
        $break2StartLabel = '';
        $break2EndLabel   = '';

        // 備考（attendances.note）
        $noteLabel = $attendance->note ?? '';

        // 「修正申請へ」などのボタンの遷移先
        // まだ専用画面が無ければ '#' のままでもOK
        $editUrl = '#';

        return view('detail', compact(
            'employeeName',
            'dateYearLabel',
            'dateDayLabel',
            'workStartLabel',
            'workEndLabel',
            'break1StartLabel',
            'break1EndLabel',
            'break2StartLabel',
            'break2EndLabel',
            'noteLabel',
            'editUrl',
        ));
    }

    /**
     * time型/日時文字列を "H:i" に整形（null のときは空文字）
     */
    private function formatTime(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable $e) {
            // 万一パースできないならそのまま返す
            return (string) $value;
        }
    }
}
