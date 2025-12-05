<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Carbon\Carbon;

class StaffController extends Controller
{
    /**
     * GET /admin/staff/list
     * 管理者用 スタッフ一覧
     */
    public function index()
    {
        $adminEmails = config('admin.emails', []);

        $staffUsers = User::query()
            ->when(!empty($adminEmails), fn($q) => $q->whereNotIn('email', $adminEmails))
            ->orderBy('name')
            ->get();

        $staffList = $staffUsers->map(function (User $u) {
            return [
                'name_label'  => $u->name,
                'email_label' => $u->email,
                // 詳細リンク → スタッフ別勤怠（月次）
                'detail_url'  => route('admin.staff.attendance', ['user' => $u->id]),
                'detail_text' => '詳細',
            ];
        });

        return view('admin.staff', [
            'pageTitle' => 'スタッフ一覧',
            'staffList' => $staffList,
        ]);
    }

    /**
     * GET /admin/staff/{user}/attendance?month=YYYY-MM
     * 管理者用 スタッフ別勤怠一覧（月次）
     *
     * 対象月の 1日〜末日まで必ず配列を作って渡す
     */
    public function attendance(Request $request, int $user)
    {
        // 対象スタッフ
        $staff = User::findOrFail($user);

        // 対象月の決定（?month=YYYY-MM）
        $rawMonth = $request->query('month');
        if ($rawMonth) {
            try {
                $targetMonth = Carbon::createFromFormat('Y-m', $rawMonth)->startOfMonth();
            } catch (\Throwable $e) {
                $targetMonth = Carbon::today()->startOfMonth();
            }
        } else {
            $targetMonth = Carbon::today()->startOfMonth();
        }

        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth   = $targetMonth->copy()->endOfMonth();

        /**
         * その月に存在する勤怠だけ取得して日付キー化
         * → 後で「全日分ループ」で埋める
         */
        $recordsByDate = Attendance::with(['time', 'total', 'breaks'])
            ->where('user_id', $staff->id)
            ->whereBetween('work_date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn (Attendance $a) =>
                Carbon::parse($a->work_date)->format('Y-m-d')
            );

        // 分を H:MM にする補助
        $fmtMinutes = function (?int $minutes): string {
            $minutes = (int)($minutes ?? 0);
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;
            return $h . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
        };

        /**
         * 対象月の「全日」配列を作る
         * 勤怠が無い日も空欄行として push
         */
        $attendances = collect();
        $cursor = $startOfMonth->copy();

        while ($cursor->lte($endOfMonth)) {
            $key        = $cursor->format('Y-m-d');
            $attendance = $recordsByDate->get($key); // あれば Attendance

            $weekdayJa = $cursor->locale('ja')->isoFormat('ddd');

            if ($attendance) {
                $time = $attendance->time;

                $startLabel = $time?->start_time
                    ? Carbon::parse($time->start_time)->format('H:i')
                    : '';

                $endLabel = $time?->end_time
                    ? Carbon::parse($time->end_time)->format('H:i')
                    : '';

                $total      = $attendance->total;
                $breakLabel = $fmtMinutes($total?->break_minutes);
                $workLabel  = $fmtMinutes($total?->total_work_minutes);

                // 詳細リンク（一般/管理者共通ルート）
                $detailUrl = Route::has('attendance.detail')
                    ? route('attendance.detail', ['id' => $attendance->id])
                    : null;
            } else {
                // 勤怠が無い日 → 空欄で表示
                $startLabel = '';
                $endLabel   = '';
                $breakLabel = '';
                $workLabel  = '';
                $detailUrl  = null;
            }

            $attendances->push([
                'date_label'  => $cursor->format('m/d') . '(' . $weekdayJa . ')',
                'start_label' => $startLabel,
                'end_label'   => $endLabel,
                'break_label' => $breakLabel,
                'total_label' => $workLabel,
                'detail_url'  => $detailUrl,
                'is_active'   => $cursor->isToday(),
            ]);

            $cursor->addDay();
        }

        // 月ナビURL
        $prevMonth = $targetMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $targetMonth->copy()->addMonth()->format('Y-m');

        return view('admin.staff_id', [
            'staffNameLabel'    => $staff->name,
            'currentMonthLabel' => $targetMonth->format('Y/m'),
            'prevMonthUrl'      => route('admin.staff.attendance', [
                'user'  => $staff->id,
                'month' => $prevMonth,
            ]),
            'nextMonthUrl'      => route('admin.staff.attendance', [
                'user'  => $staff->id,
                'month' => $nextMonth,
            ]),
            'attendances'       => $attendances,
            // ★ CSV ダウンロード用 URL を Blade に渡す
            'csvDownloadUrl'    => route('admin.staff.attendance.csv', [
                'user'  => $staff->id,
                'month' => $targetMonth->format('Y-m'),
            ]),
        ]);
    }

    /**
     * GET /admin/staff/{user}/attendance/csv?month=YYYY-MM
     * スタッフ別勤怠一覧（月次）の CSV ダウンロード
     */
    public function attendanceCsv(Request $request, int $user)
    {
        $staff = User::findOrFail($user);

        // 対象月の決定（?month=YYYY-MM）
        $rawMonth = $request->query('month');
        if ($rawMonth) {
            try {
                $targetMonth = Carbon::createFromFormat('Y-m', $rawMonth)->startOfMonth();
            } catch (\Throwable $e) {
                $targetMonth = Carbon::today()->startOfMonth();
            }
        } else {
            $targetMonth = Carbon::today()->startOfMonth();
        }

        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth   = $targetMonth->copy()->endOfMonth();

        // 画面と同じく対象月の勤怠を日付キーで取得
        $recordsByDate = Attendance::with(['time', 'total'])
            ->where('user_id', $staff->id)
            ->whereBetween('work_date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn (Attendance $a) =>
                Carbon::parse($a->work_date)->format('Y-m-d')
            );

        $fmtMinutes = function (?int $minutes): string {
            $minutes = (int)($minutes ?? 0);
            if ($minutes === 0) {
                return '';
            }
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;
            return $h . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
        };

        // ===== CSV 行データ組み立て =====
        $rows = [];
        // 1行目: ヘッダー
        $rows[] = ['日付', '出勤', '退勤', '休憩', '合計'];

        $cursor = $startOfMonth->copy();
        while ($cursor->lte($endOfMonth)) {
            $key        = $cursor->format('Y-m-d');
            $attendance = $recordsByDate->get($key);
            $weekdayJa  = $cursor->locale('ja')->isoFormat('ddd');
            $dateLabel  = $cursor->format('m/d') . '(' . $weekdayJa . ')';

            if ($attendance) {
                $time = $attendance->time;

                $startLabel = $time?->start_time
                    ? Carbon::parse($time->start_time)->format('H:i')
                    : '';

                $endLabel = $time?->end_time
                    ? Carbon::parse($time->end_time)->format('H:i')
                    : '';

                $total      = $attendance->total;
                $breakLabel = $fmtMinutes($total?->break_minutes);
                $workLabel  = $fmtMinutes($total?->total_work_minutes);
            } else {
                $startLabel = '';
                $endLabel   = '';
                $breakLabel = '';
                $workLabel  = '';
            }

            $rows[] = [
                $dateLabel,
                $startLabel,
                $endLabel,
                $breakLabel,
                $workLabel,
            ];

            $cursor->addDay();
        }

        // ファイル名: 例）2025-11_山田太郎_勤怠一覧.csv
        $fileName = sprintf(
            '%s_%s_勤怠一覧.csv',
            $targetMonth->format('Y-m'),
            $staff->name
        );

        // ===== CSV ストリームレスポンス =====
        return response()->streamDownload(
            function () use ($rows) {
                $handle = fopen('php://output', 'w');

                foreach ($rows as $row) {
                    // Excel 日本語対応: SJIS-win に変換して出力
                    $converted = array_map(
                        fn ($value) => mb_convert_encoding((string)$value, 'SJIS-win', 'UTF-8'),
                        $row
                    );
                    fputcsv($handle, $converted);
                }

                fclose($handle);
            },
            $fileName,
            [
                'Content-Type' => 'text/csv; charset=Shift_JIS',
            ]
        );
    }
}
