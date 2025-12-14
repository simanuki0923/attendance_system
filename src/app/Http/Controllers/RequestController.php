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
    /**
     * 一般ユーザー用 申請一覧
     *
     * GET /stamp_correction_request/list  (name: requests.list)
     * ?tab=pending|approved
     */
    public function index(Request $request)
    {
        $user   = Auth::user();
        $userId = $user->id;

        // -----------------------------
        // 1) タブ判定
        // -----------------------------
        $rawTab    = (string) $request->query('tab', 'pending');
        $activeTab = in_array($rawTab, ['pending', 'approved'], true) ? $rawTab : 'pending';

        $pendingTabUrl  = route('requests.list', ['tab' => 'pending']);
        $approvedTabUrl = route('requests.list', ['tab' => 'approved']);
        $pageTitle      = '申請一覧';

        // -----------------------------
        // 2) 今アクティブなタブのステータスを取得
        //    code = 'pending' or 'approved'
        // -----------------------------
        $status = ApplicationStatus::where('code', $activeTab)->first();

        if (! $status) {
            // マスタ未設定などの場合は空で返す
            return view('request', [
                'pageTitle'      => $pageTitle,
                'activeTab'      => $activeTab,
                'pendingTabUrl'  => $pendingTabUrl,
                'approvedTabUrl' => $approvedTabUrl,
                'requests'       => collect(),
            ]);
        }

        // -----------------------------
        // 3) ログインユーザーの申請 + タブに対応するステータスだけ取得
        //    → pending タブ: status.code = 'pending'
        //      approved タブ: status.code = 'approved'
        // -----------------------------
        $applications = AttendanceApplication::with([
                'attendance',
                'attendance.user',   // 勤怠の本人
                'status',
            ])
            ->where('applicant_user_id', $userId) // 自分が出した申請だけ
            ->where('status_id', $status->id)     // タブのステータスのみ
            ->orderByDesc('applied_at')
            ->get();

        // -----------------------------
        // 4) Blade(request.blade.php) が期待する配列形に整形
        // -----------------------------
        $requests = $applications->map(function (AttendanceApplication $app) {
            $attendance = $app->attendance;
            $owner      = $attendance?->user;

            // 対象日付
            $targetDateLabel = '';
            if ($attendance && $attendance->work_date) {
                $workDate        = $attendance->work_date instanceof \DateTimeInterface
                    ? Carbon::instance($attendance->work_date)
                    : Carbon::parse($attendance->work_date);
                $targetDateLabel = $workDate->format('Y/m/d');
            }

            // 申請日付
            $appliedDateLabel = '';
            if ($app->applied_at) {
                $appliedAt        = $app->applied_at instanceof \DateTimeInterface
                    ? Carbon::instance($app->applied_at)
                    : Carbon::parse($app->applied_at);
                $appliedDateLabel = $appliedAt->format('Y/m/d');
            }

            return [
                'status_label'       => $app->status?->label ?? '承認待ち',  // '承認待ち' / '承認済み'
                'name_label'         => $owner?->name ?? '',
                'target_date_label'  => $targetDateLabel,
                'reason_label'       => $app->reason ?? '',
                'applied_date_label' => $appliedDateLabel,
                // 一般ユーザーの詳細リンク → 自分用の勤怠詳細画面
                'detail_url'         => $attendance
                    ? route('attendance.detail', ['id' => $attendance->id])
                    : null,
            ];
        });

        // -----------------------------
        // 5) 画面へ返却
        // -----------------------------
        return view('request', [
            'pageTitle'      => $pageTitle,
            'activeTab'      => $activeTab,
            'pendingTabUrl'  => $pendingTabUrl,
            'approvedTabUrl' => $approvedTabUrl,
            'requests'       => $requests,
        ]);
    }
}


