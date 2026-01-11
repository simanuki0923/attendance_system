<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceDetailRequest;
use App\Models\Attendance;
use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DetailController extends Controller
{
    public function show(int $id): View
    {
        $userId = Auth::id();
        if (! $userId) {
            abort(403);
        }

        $attendance = Attendance::with([
                'user',
                'time',
                'breaks',
                'applications.status',
            ])
            ->where('user_id', $userId)
            ->findOrFail($id);

        $employeeName = $attendance->user?->name ?? '';

        $workDate = Carbon::parse($attendance->work_date);
        $dateYearLabel = $workDate->format('Y年');
        $dateDayLabel = $workDate->format('n月j日');

        $latestApplication = $attendance->applications
            ->sortByDesc('applied_at')
            ->first();

        $statusCode = $latestApplication?->status?->code ?? null;
        $statusLabel = $latestApplication?->status?->label ?? '未申請';

        $isPending = ($statusCode === ApplicationStatus::CODE_PENDING);

        $attendanceTime = $attendance->time;

        $breakOne = $attendance->breaks->firstWhere('break_no', 1);
        $breakTwo = $attendance->breaks->firstWhere('break_no', 2);

        $currentWorkStart = $this->formatTime($attendanceTime?->start_time);
        $currentWorkEnd = $this->formatTime($attendanceTime?->end_time);

        $currentBreak1Start = $this->formatTime($breakOne?->start_time);
        $currentBreak1End = $this->formatTime($breakOne?->end_time);

        $currentBreak2Start = $this->formatTime($breakTwo?->start_time);
        $currentBreak2End = $this->formatTime($breakTwo?->end_time);

        $currentNote = (string) ($attendance->note ?? '');

        if ($isPending && $latestApplication) {
            $workStartLabel = $this->formatTime($latestApplication->requested_work_start_time) ?: $currentWorkStart;
            $workEndLabel = $this->formatTime($latestApplication->requested_work_end_time) ?: $currentWorkEnd;

            $break1StartLabel = $this->formatTime($latestApplication->requested_break1_start_time) ?: $currentBreak1Start;
            $break1EndLabel = $this->formatTime($latestApplication->requested_break1_end_time) ?: $currentBreak1End;

            $break2StartLabel = $this->formatTime($latestApplication->requested_break2_start_time) ?: $currentBreak2Start;
            $break2EndLabel = $this->formatTime($latestApplication->requested_break2_end_time) ?: $currentBreak2End;

            $noteLabel = (string) ($latestApplication->requested_note ?? $currentNote);
        } else {
            $workStartLabel = $currentWorkStart;
            $workEndLabel = $currentWorkEnd;

            $break1StartLabel = $currentBreak1Start;
            $break1EndLabel = $currentBreak1End;

            $break2StartLabel = $currentBreak2Start;
            $break2EndLabel = $currentBreak2End;

            $noteLabel = $currentNote;
        }

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
            'noteLabel'         => $noteLabel,

            'statusCode'        => $statusCode,
            'statusLabel'       => $statusLabel,
            'isPending'         => $isPending,
        ]);
    }

    public function update(AttendanceDetailRequest $request, int $id): RedirectResponse
    {
        $userId = Auth::id();
        if (! $userId) {
            abort(403);
        }

        $attendance = Attendance::with(['applications.status'])
            ->where('user_id', $userId)
            ->findOrFail($id);

        $latestApplication = $attendance->applications->sortByDesc('applied_at')->first();

        if (($latestApplication?->status?->code ?? null) === ApplicationStatus::CODE_PENDING) {
            return back()
                ->withErrors(['application' => '承認待ちのため修正はできません。'])
                ->withInput();
        }

        $pendingStatusId = ApplicationStatus::query()
            ->where('code', ApplicationStatus::CODE_PENDING)
            ->value('id');

        if (! $pendingStatusId) {
            return back()
                ->withErrors(['application' => 'pending ステータスが存在しません。seed を確認してください。'])
                ->withInput();
        }

        $validated = $request->validated();

        AttendanceApplication::create([
            'attendance_id'     => $attendance->id,
            'applicant_user_id' => $userId,
            'status_id'         => $pendingStatusId,
            'reason'            => '勤怠修正申請',
            'applied_at'        => now(),

            'requested_work_start_time'   => $this->normalizeTime($validated['start_time'] ?? null),
            'requested_work_end_time'     => $this->normalizeTime($validated['end_time'] ?? null),
            'requested_break1_start_time' => $this->normalizeTime($validated['break1_start'] ?? null),
            'requested_break1_end_time'   => $this->normalizeTime($validated['break1_end'] ?? null),
            'requested_break2_start_time' => $this->normalizeTime($validated['break2_start'] ?? null),
            'requested_break2_end_time'   => $this->normalizeTime($validated['break2_end'] ?? null),
            'requested_note'              => $validated['note'] ?? null,
        ]);

        return redirect()
            ->route('attendance.detail', ['id' => $attendance->id])
            ->with('status', '修正申請を送信しました（承認待ち）');
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
        if ($value === null) {
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
