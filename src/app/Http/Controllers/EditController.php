<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceEditRequest;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\AttendanceTime;
use App\Models\AttendanceTotal;
use App\Models\ApplicationStatus;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EditController extends Controller
{
    public function show(int $id): View
    {
        $attendance = Attendance::with(['user', 'time', 'breaks', 'applications.status'])
            ->findOrFail($id);

        $employeeName = $attendance->user?->name ?? '';

        $workDate = $attendance->work_date instanceof DateTimeInterface
            ? Carbon::instance($attendance->work_date)
            : Carbon::parse($attendance->work_date);

        $dateYearLabel = $workDate->format('Y年n月j日');
        $dateDayLabel = '(' . $workDate->locale('ja')->isoFormat('ddd') . ')';

        $attendanceTime = $attendance->time;

        $breakOne = $attendance->breaks->firstWhere('break_no', 1);
        $breakTwo = $attendance->breaks->firstWhere('break_no', 2);

        $latestApplication = $attendance->applications->sortByDesc('applied_at')->first();
        $lockByPending = false;

        if ($latestApplication) {
            $lockByPending = (($latestApplication->status?->code ?? null) === ApplicationStatus::CODE_PENDING);
        }

        return view('admin.edit', [
            'attendance'       => $attendance,
            'attendanceId'     => $attendance->id,
            'employeeName'     => $employeeName,
            'dateYearLabel'    => $dateYearLabel,
            'dateDayLabel'     => $dateDayLabel,
            'workStartLabel'   => $this->formatTime($attendanceTime?->start_time),
            'workEndLabel'     => $this->formatTime($attendanceTime?->end_time),
            'break1StartLabel' => $this->formatTime($breakOne?->start_time),
            'break1EndLabel'   => $this->formatTime($breakOne?->end_time),
            'break2StartLabel' => $this->formatTime($breakTwo?->start_time),
            'break2EndLabel'   => $this->formatTime($breakTwo?->end_time),
            'noteLabel'        => (string) ($attendance->note ?? ''),
            'lockByPending'    => $lockByPending,
        ]);
    }

    public function update(AttendanceEditRequest $request, int $id): RedirectResponse
    {
        $attendance = Attendance::with(['time', 'breaks', 'total'])->findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($attendance, $validated): void {
            $attendanceTime = $attendance->time ?: new AttendanceTime(['attendance_id' => $attendance->id]);
            $attendanceTime->attendance_id = $attendance->id;
            $attendanceTime->start_time = $this->normalizeTime($validated['start_time'] ?? null);
            $attendanceTime->end_time = $this->normalizeTime($validated['end_time'] ?? null);
            $attendanceTime->save();

            $this->updateBreak($attendance->id, 1, $validated['break1_start'] ?? null, $validated['break1_end'] ?? null);
            $this->updateBreak($attendance->id, 2, $validated['break2_start'] ?? null, $validated['break2_end'] ?? null);

            $attendance->note = $validated['note'] ?? '';
            $attendance->save();

            $this->recalculateTotalDb($attendance->id);
        });

        return redirect()
            ->route('admin.attendance.detail', ['id' => $attendance->id])
            ->with('status', '勤怠詳細を更新しました。');
    }

    private function updateBreak(int $attendanceId, int $breakNo, mixed $start, mixed $end): void
    {
        $startTime = $this->normalizeTime($start);
        $endTime = $this->normalizeTime($end);

        if ($startTime === null && $endTime === null) {
            AttendanceBreak::query()
                ->where('attendance_id', $attendanceId)
                ->where('break_no', $breakNo)
                ->delete();

            return;
        }

        $minutes = 0;
        $startCarbon = $startTime ? Carbon::createFromFormat('H:i:s', $startTime) : null;
        $endCarbon = $endTime ? Carbon::createFromFormat('H:i:s', $endTime) : null;

        if ($startCarbon && $endCarbon && $endCarbon->greaterThan($startCarbon)) {
            $minutes = $startCarbon->diffInMinutes($endCarbon);
        }

        AttendanceBreak::query()->updateOrCreate(
            [
                'attendance_id' => $attendanceId,
                'break_no' => $breakNo,
            ],
            [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'minutes' => $minutes,
            ]
        );
    }

    private function recalculateTotalDb(int $attendanceId): void
    {
        $time = AttendanceTime::query()->where('attendance_id', $attendanceId)->first();

        $workMinutes = 0;

        $start = $time?->start_time ? Carbon::createFromFormat('H:i:s', $time->start_time) : null;
        $end = $time?->end_time ? Carbon::createFromFormat('H:i:s', $time->end_time) : null;

        if ($start && $end && $end->greaterThan($start)) {
            $workMinutes = $start->diffInMinutes($end);
        }

        $breakMinutes = (int) AttendanceBreak::query()->where('attendance_id', $attendanceId)->sum('minutes');
        $breakMinutes = max(0, $breakMinutes);

        $netMinutes = max(0, $workMinutes - $breakMinutes);

        AttendanceTotal::query()->updateOrCreate(
            ['attendance_id' => $attendanceId],
            [
                'break_minutes' => $breakMinutes,
                'total_work_minutes' => $netMinutes,
            ]
        );
    }

    private function normalizeTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $trimmed)) {
            return Carbon::createFromFormat('H:i', $trimmed)->format('H:i:s');
        }

        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $trimmed)) {
            return Carbon::createFromFormat('H:i:s', $trimmed)->format('H:i:s');
        }

        return null;
    }

    private function formatTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $stringValue = (string) $value;

            if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $stringValue)) {
                return Carbon::createFromFormat('H:i:s', $stringValue)->format('H:i');
            }

            if (preg_match('/^\d{1,2}:\d{2}$/', $stringValue)) {
                return Carbon::createFromFormat('H:i', $stringValue)->format('H:i');
            }

            return Carbon::parse($stringValue)->format('H:i');
        } catch (\Throwable $throwable) {
            return '';
        }
    }
}
