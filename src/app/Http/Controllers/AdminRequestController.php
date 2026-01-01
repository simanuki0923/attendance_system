<?php

namespace App\Http\Controllers;

use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;
use App\Models\AttendanceTime;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\AttendanceTotal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminRequestController extends Controller
{
    // index(), showApprove() は現状のままでOK

    public function approve(Request $request, int $attendanceCorrectRequestId)
    {
        $app = AttendanceApplication::with([
                'status',
                'attendance.time',
                'attendance.breaks',
                'attendance.total',
            ])
            ->findOrFail($attendanceCorrectRequestId);

        if (($app->status?->code ?? null) !== ApplicationStatus::CODE_PENDING) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'      => true,
                    'status'  => $app->status?->code ?? 'unknown',
                    'label'   => $app->status?->label ?? '処理済み',
                    'message' => 'この申請は既に処理済みです。',
                ]);
            }
            return back()->with('status', 'この申請は既に処理済みです。');
        }

        $approvedStatus = ApplicationStatus::where('code', ApplicationStatus::CODE_APPROVED)->firstOrFail();

        DB::transaction(function () use ($app, $approvedStatus) {
            $attendance = $app->attendance;
            if (! $attendance) {
                $app->status_id = $approvedStatus->id;
                $app->save();
                return;
            }

            // 勤怠時間 反映（requested が null のものは現状維持）
            $time = $attendance->time ?: new AttendanceTime(['attendance_id' => $attendance->id]);
            $time->attendance_id = $attendance->id;

            if ($app->requested_work_start_time !== null) {
                $time->start_time = $app->requested_work_start_time;
            }
            if ($app->requested_work_end_time !== null) {
                $time->end_time = $app->requested_work_end_time;
            }
            $time->save();

            // 休憩 反映（requested が null/null の場合はその break を削除）
            $this->applyBreak($attendance->id, 1, $app->requested_break1_start_time, $app->requested_break1_end_time);
            $this->applyBreak($attendance->id, 2, $app->requested_break2_start_time, $app->requested_break2_end_time);

            // 備考 反映（requested が null なら現状維持）
            if ($app->requested_note !== null) {
                $attendance->note = $app->requested_note;
                $attendance->save();
            }

            // 合計再計算
            $this->recalculateTotalDb($attendance->id);

            // 申請を承認済みに
            $app->status_id = $approvedStatus->id;
            $app->save();
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => true,
                'status'  => ApplicationStatus::CODE_APPROVED,
                'label'   => $approvedStatus->label ?? '承認済み',
                'message' => '承認しました。',
            ]);
        }

        return back()->with('status', '承認しました');
    }

    private function applyBreak(int $attendanceId, int $breakNo, ?string $start, ?string $end): void
    {
        $start = $this->normalizeTime($start);
        $end   = $this->normalizeTime($end);

        // 両方空なら削除（＝休憩なしにしたい）
        if ($start === null && $end === null) {
            AttendanceBreak::where('attendance_id', $attendanceId)
                ->where('break_no', $breakNo)
                ->delete();
            return;
        }

        $minutes = 0;
        $s = $this->parseTime($start);
        $e = $this->parseTime($end);
        if ($s && $e && $e->gte($s)) {
            $minutes = $s->diffInMinutes($e);
        }

        AttendanceBreak::updateOrCreate(
            ['attendance_id' => $attendanceId, 'break_no' => $breakNo],
            [
                'start_time' => $start,
                'end_time'   => $end,
                'minutes'    => max(0, (int) $minutes),
            ]
        );
    }

    private function recalculateTotalDb(int $attendanceId): void
    {
        $attendance = Attendance::with(['time', 'breaks', 'total'])->findOrFail($attendanceId);

        $workMinutes = 0;
        $start = $this->parseTime($attendance->time?->start_time);
        $end   = $this->parseTime($attendance->time?->end_time);

        if ($start && $end && $end->gte($start)) {
            $workMinutes = $start->diffInMinutes($end);
        }

        $breakMinutes = (int) ($attendance->breaks?->sum('minutes') ?? 0);
        $breakMinutes = max(0, $breakMinutes);

        $netMinutes = max(0, $workMinutes - $breakMinutes);

        $total = $attendance->total ?: new AttendanceTotal(['attendance_id' => $attendanceId]);
        $total->attendance_id      = $attendanceId;
        $total->break_minutes      = $breakMinutes;
        $total->total_work_minutes = $netMinutes;
        $total->save();
    }

    private function parseTime(?string $value): ?Carbon
    {
        if ($value === null) return null;
        $v = trim($value);
        if ($v === '') return null;

        try {
            if (preg_match('/^\d{1,2}:\d{2}$/', $v)) {
                return Carbon::createFromFormat('H:i', $v);
            }
            if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $v)) {
                return Carbon::createFromFormat('H:i:s', $v);
            }
            return Carbon::parse($v);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeTime(?string $value): ?string
    {
        $dt = $this->parseTime($value);
        return $dt ? $dt->format('H:i:s') : null;
    }
}
