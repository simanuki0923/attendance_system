<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceBreak;
use App\Models\AttendanceTotal;
use App\Models\AttendanceApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EditController extends Controller
{
    /**
     * 管理者用 勤怠詳細表示（編集）
     */
    public function show(int $id)
    {
        $attendance = Attendance::with(['user', 'time', 'total', 'breaks'])
            ->findOrFail($id);

        $user = $attendance->user;

        $workDate      = Carbon::parse($attendance->work_date);
        $dateYearLabel = $workDate->format('Y年');
        $dateDayLabel  = $workDate->format('n月j日');

        $time           = $attendance->time;
        $workStartLabel = $this->formatTime($time?->start_time);
        $workEndLabel   = $this->formatTime($time?->end_time);

        $break1 = $attendance->breaks->firstWhere('break_no', 1);
        $break2 = $attendance->breaks->firstWhere('break_no', 2);

        $break1StartLabel = $this->formatTime($break1?->start_time);
        $break1EndLabel   = $this->formatTime($break1?->end_time);
        $break2StartLabel = $this->formatTime($break2?->start_time);
        $break2EndLabel   = $this->formatTime($break2?->end_time);

        $noteLabel = $attendance->note ?? '';

        // 承認待ち申請の有無
        $hasPendingApplication = AttendanceApplication::where('attendance_id', $attendance->id)
            ->whereHas('status', function ($q) {
                $q->where('code', 'pending');
            })
            ->exists();

        return view('admin.edit', [
            'attendance'            => $attendance,
            'employeeName'          => $user?->name ?? '',
            'dateYearLabel'         => $dateYearLabel,
            'dateDayLabel'          => $dateDayLabel,
            'workStartLabel'        => $workStartLabel,
            'workEndLabel'          => $workEndLabel,
            'break1StartLabel'      => $break1StartLabel,
            'break1EndLabel'        => $break1EndLabel,
            'break2StartLabel'      => $break2StartLabel,
            'break2EndLabel'        => $break2EndLabel,
            'noteLabel'             => $noteLabel,
            'hasPendingApplication' => $hasPendingApplication,
        ]);
    }

    /**
     * 管理者による勤怠修正
     */
    public function update(Request $request, int $id)
    {
        $attendance = Attendance::with(['time', 'total', 'breaks'])
            ->findOrFail($id);

        // 承認待ちなら更新禁止
        $hasPendingApplication = AttendanceApplication::where('attendance_id', $attendance->id)
            ->whereHas('status', function ($q) {
                $q->where('code', 'pending');
            })
            ->exists();

        if ($hasPendingApplication) {
            return redirect()
                ->back()
                ->with('error', '承認待ちのため修正はできません。');
        }

        $validated = $request->validate([
            'start_time'   => ['nullable', 'date_format:H:i'],
            'end_time'     => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],
            'break1_start' => ['nullable', 'date_format:H:i'],
            'break1_end'   => ['nullable', 'date_format:H:i', 'after_or_equal:break1_start'],
            'break2_start' => ['nullable', 'date_format:H:i'],
            'break2_end'   => ['nullable', 'date_format:H:i', 'after_or_equal:break2_start'],
            'note'         => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($attendance, $validated) {

            // 出勤・退勤
            $time = $attendance->time ?: new AttendanceTime([
                'attendance_id' => $attendance->id,
            ]);

            $time->start_time = $this->normalizeTime($validated['start_time'] ?? null);
            $time->end_time   = $this->normalizeTime($validated['end_time'] ?? null);
            $time->save();

            // 休憩1 / 休憩2
            $this->updateBreak(
                $attendance,
                1,
                $validated['break1_start'] ?? null,
                $validated['break1_end'] ?? null
            );

            $this->updateBreak(
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
        });

        return redirect()
            ->route('admin.attendance.list')
            ->with('success', '勤怠を修正しました。');
    }

    /* ===== ヘルパー ===== */

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

    private function updateBreak(Attendance $attendance, int $breakNo, ?string $start, ?string $end): void
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
