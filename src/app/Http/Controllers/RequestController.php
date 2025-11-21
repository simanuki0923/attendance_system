<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

// モデルは DB 設計に合わせて用意しておく想定
use App\Models\Attendance;
use App\Models\AttendanceApplication;   // attendance_applications
use App\Models\ApplicationStatus;      // application_statuses

class RequestController extends Controller
{
    /**
     * 申請一覧画面
     *
     * ルート: GET /requests   (name: requests.list)
     * view : resources/views/request.blade.php
     *
     * クエリパラメータ:
     *   ?tab=pending|approved
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // -----------------------------
        // 1. タブ状態の決定
        // -----------------------------
        $rawTab = (string) $request->query('tab', 'pending');
        $activeTab = in_array($rawTab, ['pending', 'approved'], true) ? $rawTab : 'pending';

        // タブ切り替え用 URL
        $pendingTabUrl  = route('requests.list', ['tab' => 'pending']);
        $approvedTabUrl = route('requests.list', ['tab' => 'approved']);

        // 画面タイトル
        $pageTitle = '申請一覧';

        // -----------------------------
        // 2. 対象ステータスの取得
        // -----------------------------
        // application_statuses.code = 'pending' / 'approved' / 'rejected' を想定
        $statusCode = $activeTab === 'approved' ? 'approved' : 'pending';

        $status = ApplicationStatus::where('code', $statusCode)->first();

        if (!$status) {
            // ステータスマスタが未整備の場合でも画面が落ちないように空一覧で返す
            return view('request', [
                'pageTitle'      => $pageTitle,
                'activeTab'      => $activeTab,
                'pendingTabUrl'  => $pendingTabUrl,
                'approvedTabUrl' => $approvedTabUrl,
                'requests'       => collect(),   // 空
            ]);
        }

        // -----------------------------
        // 3. 申請データの取得
        // -----------------------------
        // ここでは「自分が申請したもの」を一覧表示する想定
        $applications = AttendanceApplication::with([
                'attendance',           // Attendance モデル
                'attendance.user',      // 勤怠のユーザー
                'status',               // ApplicationStatus モデル
            ])
            ->where('applicant_user_id', $user->id)
            ->where('status_id', $status->id)
            ->orderByDesc('applied_at')
            ->get();

        // -----------------------------
        // 4. Blade 用配列に整形
        // -----------------------------
        $requests = $applications->map(function (AttendanceApplication $app) {
            $attendance = $app->attendance;
            $owner      = $attendance?->user;
            $status     = $app->status;

            // 状態ラベル：application_statuses.label をそのまま表示
            $statusLabel = $status?->label ?? '';

            // 名前：勤怠のユーザー名（申請者本人と同じ想定）
            $nameLabel = $owner?->name ?? '';

            // 対象日付：勤怠日付（YYYY/MM/DD）
            $targetDateLabel = '';
            if ($attendance?->work_date) {
                $targetDateLabel = Carbon::parse($attendance->work_date)->format('Y/m/d');
            }

            // 申請理由
            $reasonLabel = $app->reason ?? '';

            // 申請日付（applied_at）
            $appliedDateLabel = '';
            if ($app->applied_at) {
                $appliedDateLabel = Carbon::parse($app->applied_at)->format('Y/m/d');
            }

            // ★ ここがポイント：勤怠詳細（detail.blade.php）へのリンク
            //    route('attendance.detail', ['attendance' => 勤怠ID])
            $detailUrl = null;
            if ($attendance) {
                $detailUrl = route('attendance.detail', ['attendance' => $attendance->id]);
            }

            return [
                'status_label'       => $statusLabel,
                'name_label'         => $nameLabel,
                'target_date_label'  => $targetDateLabel,
                'reason_label'       => $reasonLabel,
                'applied_date_label' => $appliedDateLabel,
                'detail_url'         => $detailUrl,
            ];
        });

        // -----------------------------
        // 5. 画面に渡す
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
