<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceDetailRequest;
use App\Models\Attendance;
use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DetailController extends Controller
{
    public function show(int $id)
    {
        $user = Auth::user();
        if ($user === null) {
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

        $latestApp = $attendance->applications
            ->sortByDesc('applied_at')
            ->first();

        $statusCode  = $latestApp?->status?->code ?? null;
        $statusLabel = $latestApp?->status?->label ?? '未申請';
        $isPending   = $statusCode === ApplicationStatus::CODE_PENDING; // 'pending'

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

    public function update(AttendanceDetailRequest $request, int $id)
    {
        $user = Auth::user();
        if ($user === null) {
            abort(403);
        }

        $attendance = Attendance::with(['applications.status'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        // 承認待ちは修正不可
        $latestApp = $attendance->applications
            ->sortByDesc('applied_at')
            ->first();

        $statusCode = $latestApp?->status?->code ?? null;

        if ($statusCode === ApplicationStatus::CODE_PENDING) {
            return back()
                ->withErrors(['application' => '承認待ちのため修正はできません。'])
                ->withInput();
        }

        $pendingStatus = ApplicationStatus::where('code', ApplicationStatus::CODE_PENDING)->firstOrFail();

        // ★ここが重要：勤怠(attendance_times / breaks / totals)は更新しない。申請だけ作る
        AttendanceApplication::create([
            'attendance_id'              => $attendance->id,
            'applicant_user_id'          => $user->id,
            'status_id'                  => $pendingStatus->id,
            'reason'                     => '勤怠修正申請',
            'applied_at'                 => now(),

            'requested_work_start_time'  => $this->normalizeTime($request->input('start_time')),
            'requested_work_end_time'    => $this->normalizeTime($request->input('end_time')),
            'requested_break1_start_time'=> $this->normalizeTime($request->input('break1_start')),
            'requested_break1_end_time'  => $this->normalizeTime($request->input('break1_end')),
            'requested_break2_start_time'=> $this->normalizeTime($request->input('break2_start')),
            'requested_break2_end_time'  => $this->normalizeTime($request->input('break2_end')),
            'requested_note'             => $request->input('note'),
        ]);

        return redirect()
            ->route('attendance.detail', ['id' => $attendance->id])
            ->with('success', '修正申請を受け付けました。');
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

    private function formatTime(?string $value): string
    {
        $dt = $this->parseTime($value);
        return $dt ? $dt->format('H:i') : '';
    }
}
