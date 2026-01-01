<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        $user   = Auth::user();
        $userId = $user->id;

        $rawTab    = (string) $request->query('tab', 'pending');
        $activeTab = in_array($rawTab, ['pending', 'approved'], true) ? $rawTab : 'pending';

        $pendingTabUrl  = route('requests.list', ['tab' => 'pending']);
        $approvedTabUrl = route('requests.list', ['tab' => 'approved']);
        $pageTitle      = '申請一覧';

        $status = ApplicationStatus::where('code', $activeTab)->first();

        if (! $status) {
            return view('request', [
                'pageTitle'      => $pageTitle,
                'activeTab'      => $activeTab,
                'pendingTabUrl'  => $pendingTabUrl,
                'approvedTabUrl' => $approvedTabUrl,
                'requests'       => collect(),
            ]);
        }

        $applications = AttendanceApplication::with([
                'attendance',
                'attendance.user',
                'status',
            ])
            ->where('applicant_user_id', $userId)
            ->where('status_id', $status->id)
            ->orderByDesc('applied_at')
            ->get();

        $requests = $applications->map(function (AttendanceApplication $app) {
            $attendance = $app->attendance;
            $owner      = $attendance?->user;

            $targetDateLabel = '';
            if ($attendance && $attendance->work_date) {
                $workDate        = $attendance->work_date instanceof \DateTimeInterface
                    ? Carbon::instance($attendance->work_date)
                    : Carbon::parse($attendance->work_date);
                $targetDateLabel = $workDate->format('Y/m/d');
            }

            $appliedDateLabel = '';
            if ($app->applied_at) {
                $appliedAt        = $app->applied_at instanceof \DateTimeInterface
                    ? Carbon::instance($app->applied_at)
                    : Carbon::parse($app->applied_at);
                $appliedDateLabel = $appliedAt->format('Y/m/d');
            }

            return [
                'status_label'       => $app->status?->label ?? '承認待ち',
                'name_label'         => $owner?->name ?? '',
                'target_date_label'  => $targetDateLabel,
                'reason_label'       => $app->reason ?? '',
                'applied_date_label' => $appliedDateLabel,
                'detail_url'         => $attendance
                    ? route('attendance.detail', ['id' => $attendance->id])
                    : null,
            ];
        });

        return view('request', [
            'pageTitle'      => $pageTitle,
            'activeTab'      => $activeTab,
            'pendingTabUrl'  => $pendingTabUrl,
            'approvedTabUrl' => $approvedTabUrl,
            'requests'       => $requests,
        ]);
    }
}


