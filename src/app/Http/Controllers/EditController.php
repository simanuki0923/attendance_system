<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceEditRequest;
use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceBreak;
use App\Models\AttendanceTotal;
use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class EditController extends Controller
{
    public function show(int $id)
    {
        $attendance = Attendance::with(['user', 'time', 'breaks', 'applications.status'])
            ->findOrFail($id);

        $employeeName = $attendance->user?->name ?? '';

        $workDate = $attendance->work_date instanceof DateTimeInterface
            ? Carbon::instance($attendance->work_date)
            : Carbon::parse($attendance->work_date);

        $dateYearLabel = $workDate->format('Y年n月j日');
        $dateDayLabel  = '(' . $workDate->locale('ja')->isoFormat('ddd') . ')';

        $time = $attendance->time;
        $workStartLabel = $this->formatTime($time?->start_time);
        $workEndLabel   = $this->formatTime($time?->end_time);

        $b1 = $attendance->breaks->firstWhere('break_no', 1);
        $b2 = $attendance->breaks->firstWhere('break_no', 2);

        $break1StartLabel = $this->formatTime($b1?->start_time);
        $break1EndLabel   = $this->formatTime($b1?->end_time);
        $break2StartLabel = $this->formatTime($b2?->start_time);
        $break2EndLabel   = $this->formatTime($b2?->end_time);

        $noteLabel = $attendance->note ?? '';

        $latestApp = $attendance->applications
            ->sortByDesc('applied_at')
            ->first();

        $lockByPending = $latestApp && (($latestApp->status?->code ?? null) === ApplicationStatus::CODE_PENDING);

        return view('admin.edit', [
            'attendance'       => $attendance,
            'attendanceId'     => $attendance->id,
            'employeeName'     => $employeeName,
            'dateYearLabel'    => $dateYearLabel,
            'dateDayLabel'     => $dateDayLabel,
            'workStartLabel'   => $workStartLabel,
            'workEndLabel'     => $workEndLabel,
            'break1StartLabel' => $break1StartLabel,
            'break1EndLabel'   => $break1EndLabel,
            'break2StartLabel' => $break2StartLabel,
            'break2EndLabel'   => $break2EndLabel,
            'noteLabel'        => $noteLabel,
            'lockByPending'    => $lockByPending,
        ]);
    }

    public function update(AttendanceEditRequest $request, int $id)
    {
        $attendance = Attendance::with(['time', 'breaks', 'total'])
            ->findOrFail($id);

        $validated = $request->validated();

        DB::transaction(function () use ($attendance, $validated) {

            // time
            $time = $attendance->time ?: new AttendanceTime([
                'attendance_id' => $attendance->id,
            ]);

            $time->attendance_id = $attendance->id;
            $time->start_time    = $this->normalizeTime($validated['start_time'] ?? null);
            $time->end_time      = $this->normalizeTime($validated['end_time'] ?? null);
            $time->save();

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

            // note（必須）
            $attendance->note = $validated['note'];
            $attendance->save();

            // ★修正：breaksのin-memoryではなくDB集計で再計算する
            $this->recalculateTotal($attendance);

            // pending申請があればrejectへ（仕様外の運用だが現行維持）
            $pendingApps = AttendanceApplication::where('attendance_id', $attendance->id)
                ->whereHas('status', function ($q) {
                    $q->where('code', ApplicationStatus::CODE_PENDING);
                })
                ->get();

            if ($pendingApps->isNotEmpty()) {
                $rejectedId = ApplicationStatus::where('code', ApplicationStatus::CODE_REJECTED)->value('id');

                if ($rejectedId) {
                    foreach ($pendingApps as $app) {
                        $app->status_id = $rejectedId;
                        $app->save();
                    }
                }
            }
        });

        return redirect()
            ->route('admin.attendance.list')
            ->with('success', '勤怠を修正しました。');
    }

    private function extractTimeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return Carbon::instance($value)->format('H:i:s');
        }
        if (is_string($value)) {
            $v = trim($value);
            return $v === '' ? null : $v;
        }
        return null;
    }

    private function normalizeTime(mixed $value): ?string
    {
        $t = $this->extractTimeString($value);
        if ($t === null) {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $t)) {
            $t .= ':00';
        }

        try {
            return Carbon::createFromFormat('H:i:s', $t)->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseTime(mixed $value): ?Carbon
    {
        $t = $this->extractTimeString($value);
        if ($t === null) {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $t)) {
            $t .= ':00';
        }

        try {
            return Carbon::createFromFormat('H:i:s', $t);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function updateBreak(Attendance $attendance, int $breakNo, mixed $start, mixed $end): void
    {
        $startNorm = $this->normalizeTime($start);
        $endNorm   = $this->normalizeTime($end);

        $break = AttendanceBreak::where('attendance_id', $attendance->id)
            ->where('break_no', $breakNo)
            ->first();

        if (! $break) {
            $break = new AttendanceBreak();
            $break->attendance_id = $attendance->id;
            $break->break_no      = $breakNo;
        }

        $break->start_time = $startNorm;
        $break->end_time   = $endNorm;

        if ($startNorm && $endNorm) {
            $s = Carbon::createFromFormat('H:i:s', $startNorm);
            $e = Carbon::createFromFormat('H:i:s', $endNorm);
            $break->minutes = max(0, $s->diffInMinutes($e));
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

        // ★修正：DBから集計（relationの古い値に引っ張られない）
        $breakMinutes = (int) AttendanceBreak::where('attendance_id', $attendance->id)->sum('minutes');
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

    private function formatTime(mixed $value): string
    {
        $dt = $this->parseTime($value);
        return $dt ? $dt->format('H:i') : '';
    }
}
