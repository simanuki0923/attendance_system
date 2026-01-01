<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceDetailRequest;
use App\Models\Attendance;
use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DetailController extends Controller
{
    public function show(int $id)
    {
        $userId = Auth::id();
        if (!$userId) {
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

        $workDate      = Carbon::parse($attendance->work_date);
        $dateYearLabel = $workDate->format('Y年');
        $dateDayLabel  = $workDate->format('n月j日');
        $latestApp = $attendance->applications
            ->sortByDesc('applied_at')
            ->first();

        $statusCode  = $latestApp?->status?->code ?? null;
        $statusLabel = $latestApp?->status?->label ?? '未申請';
        $pendingCode = defined(ApplicationStatus::class . '::CODE_PENDING')
            ? ApplicationStatus::CODE_PENDING
            : 'pending';

        $isPending = ($statusCode === $pendingCode);

        $time = $attendance->time;

        $b1 = $attendance->breaks->firstWhere('break_no', 1);
        $b2 = $attendance->breaks->firstWhere('break_no', 2);

        $currentWorkStart = $this->formatTime($time?->start_time);
        $currentWorkEnd   = $this->formatTime($time?->end_time);

        $currentB1Start = $this->formatTime($b1?->start_time);
        $currentB1End   = $this->formatTime($b1?->end_time);

        $currentB2Start = $this->formatTime($b2?->start_time);
        $currentB2End   = $this->formatTime($b2?->end_time);

        $currentNote = (string)($attendance->note ?? '');

        if ($isPending && $latestApp) {
            $workStartLabel = $this->formatTime($latestApp->requested_work_start_time) ?: $currentWorkStart;
            $workEndLabel   = $this->formatTime($latestApp->requested_work_end_time)   ?: $currentWorkEnd;

            if ($latestApp->requested_break1_start_time === null && $latestApp->requested_break1_end_time === null) {
                $break1StartLabel = '';
                $break1EndLabel   = '';
            } else {
                $break1StartLabel = $this->formatTime($latestApp->requested_break1_start_time) ?: $currentB1Start;
                $break1EndLabel   = $this->formatTime($latestApp->requested_break1_end_time)   ?: $currentB1End;
            }

            if ($latestApp->requested_break2_start_time === null && $latestApp->requested_break2_end_time === null) {
                $break2StartLabel = '';
                $break2EndLabel   = '';
            } else {
                $break2StartLabel = $this->formatTime($latestApp->requested_break2_start_time) ?: $currentB2Start;
                $break2EndLabel   = $this->formatTime($latestApp->requested_break2_end_time)   ?: $currentB2End;
            }

            $noteLabel = ($latestApp->requested_note !== null) ? (string)$latestApp->requested_note : $currentNote;
        } else {
            $workStartLabel   = $currentWorkStart;
            $workEndLabel     = $currentWorkEnd;
            $break1StartLabel = $currentB1Start;
            $break1EndLabel   = $currentB1End;
            $break2StartLabel = $currentB2Start;
            $break2EndLabel   = $currentB2End;
            $noteLabel        = $currentNote;
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

    public function update(AttendanceDetailRequest $request, int $id)
    {
        $userId = Auth::id();
        if (!$userId) {
            abort(403);
        }

        $attendance = Attendance::with(['applications.status'])
            ->where('user_id', $userId)
            ->findOrFail($id);

        $latestApp = $attendance->applications->sortByDesc('applied_at')->first();

        $pendingCode = defined(ApplicationStatus::class . '::CODE_PENDING')
            ? ApplicationStatus::CODE_PENDING
            : 'pending';

        if (($latestApp?->status?->code ?? null) === $pendingCode) {
            return back()
                ->withErrors(['application' => '承認待ちのため修正はできません。'])
                ->withInput();
        }

        $pendingStatusId = ApplicationStatus::where('code', $pendingCode)->value('id');
        if (!$pendingStatusId) {
            return back()
                ->withErrors(['application' => 'pending ステータスが存在しません。seed を確認してください。'])
                ->withInput();
        }

        $v = $request->validated();

        AttendanceApplication::create([
            'attendance_id'     => $attendance->id,
            'applicant_user_id' => $userId,
            'status_id'         => $pendingStatusId,
            'reason'            => '勤怠修正申請',
            'applied_at'        => now(),

            'requested_work_start_time'   => $this->normalizeTime($v['start_time'] ?? null),
            'requested_work_end_time'     => $this->normalizeTime($v['end_time'] ?? null),
            'requested_break1_start_time' => $this->normalizeTime($v['break1_start'] ?? null),
            'requested_break1_end_time'   => $this->normalizeTime($v['break1_end'] ?? null),
            'requested_break2_start_time' => $this->normalizeTime($v['break2_start'] ?? null),
            'requested_break2_end_time'   => $this->normalizeTime($v['break2_end'] ?? null),
            'requested_note'              => $v['note'] ?? null,
        ]);

        return redirect()
            ->route('attendance.detail', ['id' => $attendance->id])
            ->with('status', '修正申請を送信しました（承認待ち）');
    }

    private function normalizeTime($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $value)) {
            $dt = Carbon::createFromFormat('H:i', $value);
            return $dt->format('H:i:s');
        }

        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $value)) {
            $dt = Carbon::createFromFormat('H:i:s', $value);
            return $dt->format('H:i:s');
        }

        return null;
    }

    private function formatTime($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            $str = (string)$value;
            if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $str)) {
                return Carbon::createFromFormat('H:i:s', $str)->format('H:i');
            }
            if (preg_match('/^\d{1,2}:\d{2}$/', $str)) {
                return Carbon::createFromFormat('H:i', $str)->format('H:i');
            }
            return Carbon::parse($str)->format('H:i');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
