<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;

class AdminRequestController extends Controller
{
    /**
     * 管理者用 申請一覧
     * view: resources/views/admin/request.blade.php
     *
     * クエリ:
     *   ?tab=pending  → 承認待ち
     *   ?tab=approved → 承認済み
     */
    public function index(Request $request)
    {
        // ------------------------
        // どのタブを表示するか
        // ------------------------
        $activeTab = $request->query('tab', 'pending');
        if (!in_array($activeTab, ['pending', 'approved'], true)) {
            $activeTab = 'pending';
        }

        // application_statuses.code と対応
        $statusCode = $activeTab; // 'pending' or 'approved'

        // ------------------------
        // 対象ステータスの申請一覧を取得
        // ------------------------
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

        // ------------------------
        // Blade が扱いやすい形に整形
        // ------------------------
        $requests = $apps->map(function (AttendanceApplication $app) {
            $att = $app->attendance;

            return [
                // application_statuses.label 例: '承認待ち', '承認済み'
                'status_label'       => $app->status?->label ?? '承認待ち',

                // 申請者名
                'name_label'         => $app->applicant?->name ?? '不明',

                // 対象日付
                'target_date_label'  => $att?->work_date
                    ? Carbon::parse($att->work_date)->format('Y/m/d')
                    : '',

                // 申請理由
                'reason_label'       => $app->reason ?? '',

                // 申請日付
                'applied_date_label' => $app->applied_at
                    ? Carbon::parse($app->applied_at)->format('Y/m/d')
                    : '',

                // 詳細リンク → 修正申請承認画面
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

    /**
     * 修正申請承認画面 表示（管理者）
     *
     * GET /stamp_correction_request/approve/{attendance_correct_request_id}
     * view: resources/views/admin/detail.blade.php
     */
    public function showApprove(Request $request, int $attendance_correct_request_id)
    {
        $app = AttendanceApplication::with([
                'attendance.time',
                'attendance.breaks',
                'attendance.user',
                'status',
            ])
            ->findOrFail($attendance_correct_request_id);

        $attendance = $app->attendance;

        // 対応する勤怠が無い場合は 404
        if (!$attendance) {
            abort(404, '対応する勤怠データが存在しません。');
        }

        $user         = $attendance->user;
        $employeeName = $user?->name ?? '不明';

        // 日付ラベル
        $dateYearLabel = '';
        $dateDayLabel  = '';
        if ($attendance->work_date) {
            $workDate = $attendance->work_date instanceof \DateTimeInterface
                ? Carbon::instance($attendance->work_date)
                : Carbon::parse($attendance->work_date);

            $dateYearLabel = $workDate->format('Y年');
            $dateDayLabel  = $workDate->format('n月j日');
        }

        // 時刻フォーマット用クロージャ
        $formatTime = function ($value): string {
            if (!$value) {
                return '';
            }

            if ($value instanceof \DateTimeInterface) {
                return $value->format('H:i');
            }

            try {
                // DB が H:i:s 保存を想定
                return Carbon::createFromFormat('H:i:s', $value)->format('H:i');
            } catch (\Throwable $e) {
                try {
                    return Carbon::parse($value)->format('H:i');
                } catch (\Throwable $e2) {
                    return (string) $value;
                }
            }
        };

        // 出勤・退勤
        $time           = $attendance->time;
        $workStartLabel = $formatTime($time?->start_time ?? null);
        $workEndLabel   = $formatTime($time?->end_time ?? null);

        // 休憩1 / 休憩2
        $breaks = $attendance->breaks ?? collect();
        $break1 = $breaks->firstWhere('break_no', 1);
        $break2 = $breaks->firstWhere('break_no', 2);

        $break1StartLabel = $formatTime($break1?->start_time ?? null);
        $break1EndLabel   = $formatTime($break1?->end_time   ?? null);
        $break2StartLabel = $formatTime($break2?->start_time ?? null);
        $break2EndLabel   = $formatTime($break2?->end_time   ?? null);

        // 備考（勤怠テーブル側の note を表示する想定）
        $noteLabel = (string)($attendance->note ?? '');

        // ステータス（application_statuses）
        $statusCode  = $app->status?->code  ?? null;   // 'pending' / 'approved' など
        $statusLabel = $app->status?->label ?? '承認待ち';

        // ★ 承認済みかどうか（ここがボタン切り替えに効く）
        $isApproved = ($statusCode === 'approved');

        // ★ 承認ボタンのPOST先URL
        // 承認済みなら空文字にしておき、Blade 側でフォーム自体を出さないようにする
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

            // 承認ボタン制御用
            'approveUrl'        => $approveUrl,
            'isApproved'        => $isApproved,
        ]);
    }

    /**
     * 承認実行（管理者）
     *
     * POST /stamp_correction_request/approve/{attendance_correct_request_id}
     *
     * 承認時に AttendanceApplication のステータスを approved に変更し、
     * そのタイミングで勤怠合計(AttendanceTotal)を再計算して「確定」させる。
     */
    public function approve(Request $request, int $attendance_correct_request_id)
    {
        // 対象申請＋ステータス＋勤怠データ一式を取得
        $app = AttendanceApplication::with([
                'status',
                'attendance.time',
                'attendance.breaks',
                'attendance.total',
            ])
            ->findOrFail($attendance_correct_request_id);

        // すでに pending 以外なら何もしない
        if (($app->status?->code ?? null) !== 'pending') {

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

        // 承認ステータス取得（application_statuses.code = 'approved'）
        $approvedStatus = ApplicationStatus::where('code', 'approved')->firstOrFail();

        // --------------------------------------------------
        // 1) 勤怠データの合計を「承認タイミング」で確定計算する
        // --------------------------------------------------
        $attendance = $app->attendance;

        if ($attendance) {
            // 時刻を Carbon に正規化するクロージャ
            $normalizeTime = function ($value): ?Carbon {
                if (!$value) {
                    return null;
                }

                if ($value instanceof \DateTimeInterface) {
                    return Carbon::instance($value);
                }

                try {
                    // H:i:s 固定の想定
                    return Carbon::createFromFormat('H:i:s', $value);
                } catch (\Throwable $e) {
                    try {
                        return Carbon::parse($value);
                    } catch (\Throwable $e2) {
                        return null;
                    }
                }
            };

            // 出勤・退勤
            $time  = $attendance->time;
            $start = $normalizeTime($time?->start_time ?? null);
            $end   = $normalizeTime($time?->end_time   ?? null);

            // 休憩合計
            $breakMinutes = 0;
            $breaks = $attendance->breaks ?? collect();
            foreach ($breaks as $break) {
                $bStart = $normalizeTime($break->start_time ?? null);
                $bEnd   = $normalizeTime($break->end_time   ?? null);

                if ($bStart && $bEnd) {
                    $minutes = $bEnd->diffInMinutes($bStart, false);
                    if ($minutes > 0) {
                        $breakMinutes += $minutes;
                    }
                }
            }
            $breakMinutes = max(0, $breakMinutes);

            // 実労働時間（分）
            $workMinutes = null;
            if ($start && $end) {
                $totalMinutes = $end->diffInMinutes($start, false);
                $totalMinutes = max(0, $totalMinutes);
                $workMinutes  = max(0, $totalMinutes - $breakMinutes);
            }

            // attendance_totals を更新（無ければ作成）
            $total = $attendance->total;
            if (!$total) {
                $total = new \App\Models\AttendanceTotal();
                $total->attendance_id = $attendance->id;
            }

            $total->break_minutes      = $breakMinutes;
            $total->total_work_minutes = $workMinutes;
            $total->save();
        }

        // --------------------------------------------------
        // 2) 申請ステータスを approved に更新
        // --------------------------------------------------
        $app->status_id = $approvedStatus->id;
        $app->save();

        // ★ここで DB 上は approved になり、
        //   再度 showApprove() で開いたとき $isApproved = true となる
        //   → Blade 側で「承認済み」ボタン（disabled）を表示

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
