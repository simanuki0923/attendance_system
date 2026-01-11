<?php

namespace App\Http\Controllers;

use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $userId = $user->id;

        $rawTab = (string) $request->query('tab', ApplicationStatus::CODE_PENDING);

        $allowedTabs = [ApplicationStatus::CODE_PENDING, ApplicationStatus::CODE_APPROVED];
        $activeTab = in_array($rawTab, $allowedTabs, true) ? $rawTab : ApplicationStatus::CODE_PENDING;

        $pendingTabUrl = route('requests.list', ['tab' => ApplicationStatus::CODE_PENDING]);
        $approvedTabUrl = route('requests.list', ['tab' => ApplicationStatus::CODE_APPROVED]);
        $pageTitle = '申請一覧';

        $status = ApplicationStatus::query()->where('code', $activeTab)->first();

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

        $requests = $applications->map(function (AttendanceApplication $application): array {
            $attendance = $application->attendance;
            $attendanceOwner = $attendance?->user;

            $targetDateLabel = '';
            if ($attendance && $attendance->work_date) {
                $workDate = $attendance->work_date instanceof \DateTimeInterface
                    ? Carbon::instance($attendance->work_date)
                    : Carbon::parse($attendance->work_date);

                $targetDateLabel = $workDate->format('Y/m/d');
            }

            $appliedDateLabel = '';
            if ($application->applied_at) {
                $appliedAt = $application->applied_at instanceof \DateTimeInterface
                    ? Carbon::instance($application->applied_at)
                    : Carbon::parse($application->applied_at);

                $appliedDateLabel = $appliedAt->format('Y/m/d');
            }

            return [
                'status_label'       => $application->status?->label ?? '承認待ち',
                'name_label'         => $attendanceOwner?->name ?? '',
                'target_date_label'  => $targetDateLabel,
                'reason_label'       => $application->reason ?? '',
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
