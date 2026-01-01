<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceApplication;
use App\Models\AttendanceBreak;
use App\Models\AttendanceTime;
use App\Models\AttendanceTotal;
use App\Models\ApplicationStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminRequestController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = (string) $request->query('tab', ApplicationStatus::CODE_PENDING);
        if (!in_array($activeTab, [ApplicationStatus::CODE_PENDING, ApplicationStatus::CODE_APPROVED], true)) {
            $activeTab = ApplicationStatus::CODE_PENDING;
        }

        $apps = AttendanceApplication::with([
                'attendance.user',
                'applicant',
                'status',
            ])
            ->whereHas('status', function ($q) use ($activeTab) {
                $q->where('code', $activeTab);
            })
            ->orderByDesc('applied_at')
            ->get();

        $requests = $apps->map(function (AttendanceApplication $app) {
            $att = $app->attendance;

            $targetDateLabel = $att?->work_date
                ? Carbon::parse($att->work_date)->format('Y/m/d')
                : '';

            $appliedDateLabel = $app->applied_at
                ? Carbon::parse($app->applied_at)->format('Y/m/d')
                : '';

            return [
                'status_label'       => $app->status?->label ?? '承認待ち',
                'name_label'         => $app->applicant?->name ?? ($att?->user?->name ?? '不明'),
                'target_date_label'  => $targetDateLabel,
                'reason_label'       => $app->reason ?? '',
                'applied_date_label' => $appliedDateLabel,
                'detail_url'         => route('stamp_correction_request.approve.show', [
                    'attendance_correct_request_id' => $app->id,
                ]),
            ];
        });

        return view('admin.request', [
            'pageTitle'      => '申請一覧（管理者）',
            'activeTab'      => $activeTab,
            'pendingTabUrl'  => route('requests.list', ['tab' => ApplicationStatus::CODE_PENDING]),
            'approvedTabUrl' => route('requests.list', ['tab' => ApplicationStatus::CODE_APPROVED]),
            'requests'       => $requests,
        ]);
    }

    public function showApprove(Request $request, int $attendanceCorrectRequestId)
    {
        $app = AttendanceApplication::with([
                'attendance.user',
                'attendance.time',
                'attendance.breaks',
                'status',
            ])
            ->findOrFail($attendanceCorrectRequestId);

        $attendance = $app->attendance;
        if (!$attendance) {
            abort(404, '対応する勤怠データが存在しません。');
        }

        $employeeName = $attendance->user?->name ?? '不明';

        $workDate = $attendance->work_date instanceof \DateTimeInterface
            ? Carbon::instance($attendance->work_date)
            : Carbon::parse($attendance->work_date);

        $dateYearLabel = $workDate->format('Y年');
        $dateDayLabel  = $workDate->format('n月j日');

        $time = $attendance->time;

        $b1 = $attendance->breaks->firstWhere('break_no', 1);
        $b2 = $attendance->breaks->firstWhere('break_no', 2);

        $currentWorkStart = $time?->start_time;
        $currentWorkEnd   = $time?->end_time;
        $currentB1Start   = $b1?->start_time;
        $currentB1End     = $b1?->end_time;
        $currentB2Start   = $b2?->start_time;
        $currentB2End     = $b2?->end_time;
        $currentNote      = $attendance->note;

        $workStartLabel = $this->formatTime($app->requested_work_start_time ?? $currentWorkStart);
        $workEndLabel   = $this->formatTime($app->requested_work_end_time   ?? $currentWorkEnd);

        if ($app->requested_break1_start_time === null && $app->requested_break1_end_time === null) {
            $break1StartLabel = '';
            $break1EndLabel   = '';
        } else {
            $break1StartLabel = $this->formatTime($app->requested_break1_start_time ?? $currentB1Start);
            $break1EndLabel   = $this->formatTime($app->requested_break1_end_time   ?? $currentB1End);
        }

        if ($app->requested_break2_start_time === null && $app->requested_break2_end_time === null) {
            $break2StartLabel = '';
            $break2EndLabel   = '';
        } else {
            $break2StartLabel = $this->formatTime($app->requested_break2_start_time ?? $currentB2Start);
            $break2EndLabel   = $this->formatTime($app->requested_break2_end_time   ?? $currentB2End);
        }

        $noteLabel = (string) (($app->requested_note !== null) ? $app->requested_note : ($currentNote ?? ''));

        $statusCode  = $app->status?->code ?? ApplicationStatus::CODE_PENDING;
        $statusLabel = $app->status?->label ?? '承認待ち';
        $isApproved  = ($statusCode === ApplicationStatus::CODE_APPROVED);

        $approveUrl = $isApproved ? '' : route('stamp_correction_request.approve', [
            'attendance_correct_request_id' => $app->id,
        ]);

        return view('admin.detail', [
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
            'statusLabel'      => $statusLabel,
            'statusCode'       => $statusCode,
            'approveUrl'       => $approveUrl,
            'isApproved'       => $isApproved,
        ]);
    }

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

            if (!$attendance) {
                $app->status_id = $approvedStatus->id;
                $app->save();
                return;
            }

            $time = $attendance->time ?: new AttendanceTime(['attendance_id' => $attendance->id]);
            $time->attendance_id = $attendance->id;
            $time->start_time    = $this->normalizeTime($app->requested_work_start_time);
            $time->end_time      = $this->normalizeTime($app->requested_work_end_time);
            $time->save();

            $this->applyBreak($attendance->id, 1, $app->requested_break1_start_time, $app->requested_break1_end_time);
            $this->applyBreak($attendance->id, 2, $app->requested_break2_start_time, $app->requested_break2_end_time);

            $attendance->note = $app->requested_note;
            $attendance->save();

            $this->recalculateTotalDb($attendance->id);

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
        $attendance = Attendance::with(['time', 'total'])->findOrFail($attendanceId);

        $workMinutes = 0;
        $start = $this->parseTime($attendance->time?->start_time);
        $end   = $this->parseTime($attendance->time?->end_time);
        if ($start && $end && $end->greaterThan($start)) {
            $workMinutes = $start->diffInMinutes($end);
        }

        $breakMinutes = (int) AttendanceBreak::where('attendance_id', $attendanceId)->sum('minutes');
        $breakMinutes = max(0, $breakMinutes);

        $netMinutes = max(0, $workMinutes - $breakMinutes);

        $total = $attendance->total ?: new AttendanceTotal(['attendance_id' => $attendanceId]);
        $total->attendance_id      = $attendanceId;
        $total->break_minutes      = $breakMinutes;
        $total->total_work_minutes = $netMinutes;
        $total->save();
    }

    private function normalizeTime($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i:s', $value)->format('H:i:s');
        } catch (\Throwable $e) {
            try {
                return Carbon::createFromFormat('H:i', $value)->format('H:i:s');
            } catch (\Throwable $e2) {
                return null;
            }
        }
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

    private function formatTime($value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('H:i');
        }

        $norm = $this->normalizeTime($value);
        if ($norm === null) {
            return '';
        }

        $dt = $this->parseTime($norm);
        return $dt ? $dt->format('H:i') : '';
    }
}
