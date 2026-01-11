<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceApplication;
use App\Models\AttendanceBreak;
use App\Models\AttendanceTime;
use App\Models\AttendanceTotal;
use App\Models\ApplicationStatus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminRequestController extends Controller
{
    public function index(Request $request): View
    {
        $activeTab = (string) $request->query('tab', ApplicationStatus::CODE_PENDING);

        if (! in_array($activeTab, [ApplicationStatus::CODE_PENDING, ApplicationStatus::CODE_APPROVED], true)) {
            $activeTab = ApplicationStatus::CODE_PENDING;
        }

        $applications = AttendanceApplication::with([
                'attendance.user',
                'applicant',
                'status',
            ])
            ->whereHas('status', function ($query) use ($activeTab): void {
                $query->where('code', $activeTab);
            })
            ->orderByDesc('applied_at')
            ->get();

        $requests = $applications->map(function (AttendanceApplication $application): array {
            $attendance = $application->attendance;

            $targetDateLabel = $attendance?->work_date
                ? Carbon::parse($attendance->work_date)->format('Y/m/d')
                : '';

            $appliedDateLabel = $application->applied_at
                ? Carbon::parse($application->applied_at)->format('Y/m/d')
                : '';

            return [
                'status_label'       => $application->status?->label ?? '承認待ち',
                'name_label'         => $application->applicant?->name ?? ($attendance?->user?->name ?? '不明'),
                'target_date_label'  => $targetDateLabel,
                'reason_label'       => $application->reason ?? '',
                'applied_date_label' => $appliedDateLabel,
                'detail_url'         => route('stamp_correction_request.approve.show', [
                    'attendance_correct_request_id' => $application->id,
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

    public function showApprove(Request $request, int $attendanceCorrectRequestId): View
    {
        $application = AttendanceApplication::with([
                'attendance.user',
                'attendance.time',
                'attendance.breaks',
                'status',
            ])
            ->findOrFail($attendanceCorrectRequestId);

        $attendance = $application->attendance;
        if (! $attendance) {
            abort(404, '対応する勤怠データが存在しません。');
        }

        $employeeName = $attendance->user?->name ?? '不明';

        $workDate = $attendance->work_date instanceof \DateTimeInterface
            ? Carbon::instance($attendance->work_date)
            : Carbon::parse($attendance->work_date);

        $dateYearLabel = $workDate->format('Y年');
        $dateDayLabel = $workDate->format('n月j日');

        $attendanceTime = $attendance->time;

        $breakOne = $attendance->breaks->firstWhere('break_no', 1);
        $breakTwo = $attendance->breaks->firstWhere('break_no', 2);

        $currentWorkStart = $attendanceTime?->start_time;
        $currentWorkEnd = $attendanceTime?->end_time;

        $currentBreak1Start = $breakOne?->start_time;
        $currentBreak1End = $breakOne?->end_time;

        $currentBreak2Start = $breakTwo?->start_time;
        $currentBreak2End = $breakTwo?->end_time;

        $currentNote = $attendance->note;

        $workStartLabel = $this->formatTime($application->requested_work_start_time ?? $currentWorkStart);
        $workEndLabel = $this->formatTime($application->requested_work_end_time ?? $currentWorkEnd);

        if ($application->requested_break1_start_time === null && $application->requested_break1_end_time === null) {
            $break1StartLabel = '';
            $break1EndLabel = '';
        } else {
            $break1StartLabel = $this->formatTime($application->requested_break1_start_time ?? $currentBreak1Start);
            $break1EndLabel = $this->formatTime($application->requested_break1_end_time ?? $currentBreak1End);
        }

        if ($application->requested_break2_start_time === null && $application->requested_break2_end_time === null) {
            $break2StartLabel = '';
            $break2EndLabel = '';
        } else {
            $break2StartLabel = $this->formatTime($application->requested_break2_start_time ?? $currentBreak2Start);
            $break2EndLabel = $this->formatTime($application->requested_break2_end_time ?? $currentBreak2End);
        }

        $noteLabel = (string) (($application->requested_note !== null) ? $application->requested_note : ($currentNote ?? ''));

        $statusCode = $application->status?->code ?? ApplicationStatus::CODE_PENDING;
        $statusLabel = $application->status?->label ?? '承認待ち';
        $isApproved = ($statusCode === ApplicationStatus::CODE_APPROVED);

        $approveUrl = $isApproved ? '' : route('stamp_correction_request.approve', [
            'attendance_correct_request_id' => $application->id,
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

    public function approve(Request $request, int $attendanceCorrectRequestId): JsonResponse|RedirectResponse
    {
        $application = AttendanceApplication::with([
                'status',
                'attendance.time',
                'attendance.breaks',
                'attendance.total',
            ])
            ->findOrFail($attendanceCorrectRequestId);

        if (($application->status?->code ?? null) !== ApplicationStatus::CODE_PENDING) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok'      => true,
                    'status'  => $application->status?->code ?? 'unknown',
                    'label'   => $application->status?->label ?? '処理済み',
                    'message' => 'この申請は既に処理済みです。',
                ]);
            }

            return back()->with('status', 'この申請は既に処理済みです。');
        }

        $approvedStatus = ApplicationStatus::query()
            ->where('code', ApplicationStatus::CODE_APPROVED)
            ->firstOrFail();

        DB::transaction(function () use ($application, $approvedStatus): void {
            $attendance = $application->attendance;

            if (! $attendance) {
                $application->status_id = $approvedStatus->id;
                $application->save();

                return;
            }

            $attendanceTime = $attendance->time ?: new AttendanceTime(['attendance_id' => $attendance->id]);
            $attendanceTime->attendance_id = $attendance->id;
            $attendanceTime->start_time = $this->normalizeTime($application->requested_work_start_time);
            $attendanceTime->end_time = $this->normalizeTime($application->requested_work_end_time);
            $attendanceTime->save();

            $this->applyBreak($attendance->id, 1, $application->requested_break1_start_time, $application->requested_break1_end_time);
            $this->applyBreak($attendance->id, 2, $application->requested_break2_start_time, $application->requested_break2_end_time);

            $attendance->note = $application->requested_note;
            $attendance->save();

            $this->recalculateTotalDb($attendance->id);

            $application->status_id = $approvedStatus->id;
            $application->save();
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

        $startCarbon = $this->parseTime($startTime);
        $endCarbon = $this->parseTime($endTime);

        if ($startCarbon && $endCarbon && $endCarbon->gte($startCarbon)) {
            $minutes = $startCarbon->diffInMinutes($endCarbon);
        }

        AttendanceBreak::updateOrCreate(
            ['attendance_id' => $attendanceId, 'break_no' => $breakNo],
            [
                'start_time' => $startTime,
                'end_time'   => $endTime,
                'minutes'    => max(0, (int) $minutes),
            ]
        );
    }

    private function recalculateTotalDb(int $attendanceId): void
    {
        $attendance = Attendance::with(['time', 'total'])->findOrFail($attendanceId);

        $workMinutes = 0;
        $workStart = $this->parseTime($attendance->time?->start_time);
        $workEnd = $this->parseTime($attendance->time?->end_time);

        if ($workStart && $workEnd && $workEnd->greaterThan($workStart)) {
            $workMinutes = $workStart->diffInMinutes($workEnd);
        }

        $breakMinutes = (int) AttendanceBreak::query()->where('attendance_id', $attendanceId)->sum('minutes');
        $breakMinutes = max(0, $breakMinutes);

        $netMinutes = max(0, $workMinutes - $breakMinutes);

        $attendanceTotal = $attendance->total ?: new AttendanceTotal(['attendance_id' => $attendanceId]);
        $attendanceTotal->attendance_id = $attendanceId;
        $attendanceTotal->break_minutes = $breakMinutes;
        $attendanceTotal->total_work_minutes = $netMinutes;
        $attendanceTotal->save();
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

        try {
            return Carbon::createFromFormat('H:i:s', $trimmed)->format('H:i:s');
        } catch (\Throwable $throwable) {
            try {
                return Carbon::createFromFormat('H:i', $trimmed)->format('H:i:s');
            } catch (\Throwable $throwable2) {
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
        } catch (\Throwable $throwable) {
            try {
                return Carbon::createFromFormat('H:i', $value);
            } catch (\Throwable $throwable2) {
                return null;
            }
        }
    }

    private function formatTime(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('H:i');
        }

        $normalized = $this->normalizeTime($value);
        if ($normalized === null) {
            return '';
        }

        $parsed = $this->parseTime($normalized);

        return $parsed ? $parsed->format('H:i') : '';
    }
}
