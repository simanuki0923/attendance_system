<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;
use App\Models\AttendanceTotal;

class AdminRequestController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'pending');
        if (!in_array($activeTab, ['pending', 'approved'], true)) {
            $activeTab = 'pending';
        }

        $statusCode = $activeTab;

        $apps = AttendanceApplication::with([
                'attendance.time',
                'attendance.breaks',
                'attendance.user',
                'applicant',
                'status',
            ])
            ->whereHas('status', function ($q) use ($statusCode) {
                $q->where('code', $statusCode);
            })
            ->orderByDesc('applied_at')
            ->get();

        $requests = $apps->map(function (AttendanceApplication $app) {
            $att = $app->attendance;

            return [
                'status_label'       => $app->status?->label ?? '承認待ち',
                'name_label'         => $app->applicant?->name ?? '不明',
                'target_date_label'  => $att?->work_date
                    ? Carbon::parse($att->work_date)->format('Y/m/d')
                    : '',
                'reason_label'       => $app->reason ?? '',
                'applied_date_label' => $app->applied_at
                    ? Carbon::parse($app->applied_at)->format('Y/m/d')
                    : '',
                'detail_url'         => route(
                    'stamp_correction_request.approve.show',
                    ['attendance_correct_request_id' => $app->id]
                ),
            ];
        });

        return view('admin.request', [
            'pageTitle'      => '申請一覧（管理者）',
            'activeTab'      => $activeTab,
            'pendingTabUrl'  => route('requests.list', ['tab' => 'pending']),
            'approvedTabUrl' => route('requests.list', ['tab' => 'approved']),
            'requests'       => $requests,
        ]);
    }

    public function showApprove(Request $request, int $attendanceCorrectRequestId)
    {
        $app = AttendanceApplication::with([
                'attendance.time',
                'attendance.breaks',
                'attendance.user',
                'status',
            ])
            ->findOrFail($attendanceCorrectRequestId);

        $attendance = $app->attendance;

        if (! $attendance) {
            abort(404, '対応する勤怠データが存在しません。');
        }

        $user         = $attendance->user;
        $employeeName = $user?->name ?? '不明';

        $dateYearLabel = '';
        $dateDayLabel  = '';
        if ($attendance->work_date) {
            $workDate = $attendance->work_date instanceof \DateTimeInterface
                ? Carbon::instance($attendance->work_date)
                : Carbon::parse($attendance->work_date);

            $dateYearLabel = $workDate->format('Y年');
            $dateDayLabel  = $workDate->format('n月j日');
        }

        $formatTime = function ($value): string {
            if (! $value) {
                return '';
            }

            if ($value instanceof \DateTimeInterface) {
                return $value->format('H:i');
            }

            try {
                return Carbon::createFromFormat('H:i:s', $value)->format('H:i');
            } catch (\Throwable $e) {
                try {
                    return Carbon::parse($value)->format('H:i');
                } catch (\Throwable $e2) {
                    return (string) $value;
                }
            }
        };

        $time           = $attendance->time;
        $workStartLabel = $formatTime($time?->start_time ?? null);
        $workEndLabel   = $formatTime($time?->end_time ?? null);
        $breaks = $attendance->breaks ?? collect();
        $break1 = $breaks->firstWhere('break_no', 1);
        $break2 = $breaks->firstWhere('break_no', 2);
        $break1StartLabel = $formatTime($break1?->start_time ?? null);
        $break1EndLabel   = $formatTime($break1?->end_time   ?? null);
        $break2StartLabel = $formatTime($break2?->start_time ?? null);
        $break2EndLabel   = $formatTime($break2?->end_time   ?? null);
        $noteLabel = (string) ($attendance->note ?? '');
        $statusCode  = $app->status?->code  ?? null;
        $statusLabel = $app->status?->label ?? '承認待ち';
        $isApproved = ($statusCode === ApplicationStatus::CODE_APPROVED);
        $approveUrl = $isApproved
            ? ''
            : route('stamp_correction_request.approve', [
                'attendance_correct_request_id' => $app->id,
            ]);

        return view('admin.detail', [
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

            'statusLabel'       => $statusLabel,
            'statusCode'        => $statusCode,

            'approveUrl'        => $approveUrl,
            'isApproved'        => $isApproved,
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
        $attendance = $app->attendance;

        if ($attendance) {
            $parseTime = function ($value): ?Carbon {
                if (! $value) {
                    return null;
                }
                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format('H:i:s');
                }

                try {
                    return Carbon::createFromFormat('H:i:s', (string) $value);
                } catch (\Throwable $e) {
                    try {
                        return Carbon::createFromFormat('H:i', (string) $value);
                    } catch (\Throwable $e2) {
                        return null;
                    }
                }
            };

            $time  = $attendance->time;
            $start = $parseTime($time?->start_time ?? null);
            $end   = $parseTime($time?->end_time ?? null);

            $workMinutes = 0;
            if ($start && $end && $end->greaterThan($start)) {
                $workMinutes = $start->diffInMinutes($end);
            }

            $breakMinutes = (int) ($attendance->breaks?->sum('minutes') ?? 0);
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

        $app->status_id = $approvedStatus->id;
        $app->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => true,
                'status'  => 'approved',
                'label'   => $approvedStatus->label ?? '承認済み',
                'message' => '申請を承認しました。',
            ]);
        }

        return back()->with('status', '申請を承認しました。');
    }
}


