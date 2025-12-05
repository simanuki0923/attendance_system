<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceTotal;
use App\Models\AttendanceBreak;
use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DetailtController extends Controller
{
    /**
     * 勤怠詳細（一般ユーザー）
     */
    public function show(int $id)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $attendance = Attendance::with([
                'time',
                'total',
                'applications.status',
                'breaks',
            ])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $employeeName = $user->name;

        // 日付
        $workDate      = Carbon::parse($attendance->work_date);
        $dateYearLabel = $workDate->format('Y年');
        $dateDayLabel  = $workDate->format('n月j日');

        // 出勤・退勤
        $time           = $attendance->time;
        $workStartLabel = $this->formatTime($time?->start_time);
        $workEndLabel   = $this->formatTime($time?->end_time);

        // 休憩1 / 休憩2
        $break1 = $attendance->breaks->firstWhere('break_no', 1);
        $break2 = $attendance->breaks->firstWhere('break_no', 2);

        $break1StartLabel = $this->formatTime($break1?->start_time);
        $break1EndLabel   = $this->formatTime($break1?->end_time);

        $break2StartLabel = $this->formatTime($break2?->start_time);
        $break2EndLabel   = $this->formatTime($break2?->end_time);

        // 最新の申請
        $latestApp = $attendance->applications
            ->sortByDesc('applied_at')
            ->first();

        $statusCode  = $latestApp?->status?->code ?? null;
        $statusLabel = $latestApp?->status?->label ?? '未申請';
        $isPending   = $statusCode === 'pending';

        return view('detail', [
            'attendance'        => $attendance,
            'attendanceId'      => $attendance->id,
            'employeeName'      => $employeeName,
            'dateYearLabel'     => $dateYearLabel,
            'dateDayLabel'      => $dateDayLabel,
            'workStartLabel'    => $workStartLabel,
            'workEndLabel'      => $workEndLabel,
            'break1StartLabel'  => $break1StartLabel,
            'break1EndLabel'    => $break1EndLabel,
            'break2StartLabel'  => $break2StartLabel,
            'break2EndLabel'    => $break2EndLabel,
            'noteLabel'         => $attendance->note ?? '',
            'statusCode'        => $statusCode,
            'statusLabel'       => $statusLabel,
            'isPending'         => $isPending,
        ]);
    }

    /**
     * 日付指定で詳細表示
     *  - なければ勤怠レコードを自動作成
     */
    public function showByDate(string $date)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        try {
            $targetDate = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            abort(404);
        }

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $targetDate->toDateString())
            ->first();

        if (!$attendance) {
            $attendance = Attendance::create([
                'user_id'   => $user->id,
                'work_date' => $targetDate->toDateString(),
                'note'      => null,
            ]);

            AttendanceTime::create([
                'attendance_id' => $attendance->id,
                'start_time'    => null,
                'end_time'      => null,
            ]);

            AttendanceTotal::create([
                'attendance_id'      => $attendance->id,
                'break_minutes'      => 0,
                'total_work_minutes' => 0,
            ]);
        }

        return $this->show($attendance->id);
    }

    /**
     * 勤怠詳細更新（一般ユーザー）
     *  - 修正内容を反映しつつ「修正申請」を pending で作成
     */
    public function update(Request $request, int $id)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $attendance = Attendance::with(['time', 'total', 'breaks', 'applications.status'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        // 既に pending なら画面・サーバ両方でロック
        $latestApp = $attendance->applications
            ->sortByDesc('applied_at')
            ->first();

        if ($latestApp && $latestApp->status && $latestApp->status->code === 'pending') {
            return back()
                ->withErrors(['application' => '承認待ちのため修正はできません。'])
                ->withInput();
        }

        $validated = $request->validate(
            [
                'start_time'    => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
                'end_time'      => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
                'break1_start'  => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
                'break1_end'    => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
                'break2_start'  => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
                'break2_end'    => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
                'note'          => ['nullable', 'string', 'max:255'],
            ]
        );

        // 出勤・退勤
        $time = $attendance->time ?: new AttendanceTime([
            'attendance_id' => $attendance->id,
        ]);
        $time->start_time = $this->normalizeTime($validated['start_time'] ?? null);
        $time->end_time   = $this->normalizeTime($validated['end_time'] ?? null);
        $time->save();

        // 休憩1 / 休憩2
        $this->saveBreak(
            $attendance,
            1,
            $validated['break1_start'] ?? null,
            $validated['break1_end'] ?? null
        );
        $this->saveBreak(
            $attendance,
            2,
            $validated['break2_start'] ?? null,
            $validated['break2_end'] ?? null
        );

        // 備考
        $attendance->note = $validated['note'] ?? null;
        $attendance->save();

        // 合計再計算
        $attendance->load(['time', 'breaks', 'total']);
        $this->recalculateTotal($attendance);

        // 修正申請（pending）
        $pendingStatus = ApplicationStatus::where('code', 'pending')->first();

        AttendanceApplication::create([
            'attendance_id'     => $attendance->id,
            'applicant_user_id' => $user->id,
            'status_id'         => $pendingStatus?->id,
            'reason'            => '勤怠修正申請',
            'applied_at'        => Carbon::now(),
        ]);

        return redirect()
            ->route('attendance.detail', ['id' => $attendance->id])
            ->with('status', '修正申請を受け付けました。');
    }

    /* ========= ここからヘルパー ========= */

    private function normalizeTime(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            $dt = Carbon::createFromFormat('H:i', $value);
        } catch (\Throwable $e) {
            try {
                $dt = Carbon::createFromFormat('H:i:s', $value);
            } catch (\Throwable $e2) {
                return null;
            }
        }

        return $dt->format('H:i:s');
    }

    private function parseTime(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i:s', $value);
        } catch (\Throwable $e) {
            try {
                return Carbon::createFromFormat('H:i', $value);
            } catch (\Throwable $e2) {
                return null;
            }
        }
    }

    private function saveBreak(Attendance $attendance, int $breakNo, ?string $start, ?string $end): void
    {
        $startNorm = $this->normalizeTime($start);
        $endNorm   = $this->normalizeTime($end);

        $break = $attendance->breaks->firstWhere('break_no', $breakNo);

        // 両方空なら削除
        if ($startNorm === null && $endNorm === null) {
            if ($break) {
                $break->delete();
            }
            return;
        }

        if (!$break) {
            $break = new AttendanceBreak();
            $break->attendance_id = $attendance->id;
            $break->break_no      = $breakNo;
        }

        $break->start_time = $startNorm;
        $break->end_time   = $endNorm;

        if ($startNorm && $endNorm) {
            $s = Carbon::createFromFormat('H:i:s', $startNorm);
            $e = Carbon::createFromFormat('H:i:s', $endNorm);
            $break->minutes = max(0, $e->diffInMinutes($s));
        } else {
            $break->minutes = 0;
        }

        $break->save();
    }

    private function recalculateTotal(Attendance $attendance): void
    {
        $time  = $attendance->time;
        $start = $this->parseTime($time?->start_time);
        $end   = $this->parseTime($time?->end_time);

        $workMinutes = 0;
        if ($start && $end && $end->greaterThan($start)) {
            $workMinutes = $start->diffInMinutes($end);
        }

        // 休憩は minutes カラムの合計
        $breakMinutes = (int)$attendance->breaks->sum('minutes');
        $breakMinutes = max(0, $breakMinutes);

        $netMinutes = max(0, $workMinutes - $breakMinutes);

        $total = $attendance->total ?: new AttendanceTotal([
            'attendance_id' => $attendance->id,
        ]);

        $total->attendance_id      = $attendance->id;
        $total->break_minutes      = $breakMinutes;
        $total->total_work_minutes = $netMinutes;
        $total->save();
    }

    private function formatTime(?string $value): string
    {
        $dt = $this->parseTime($value);
        return $dt ? $dt->format('H:i') : '';
    }
}
