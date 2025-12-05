<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceTotal;
use App\Models\AttendanceBreak;

// ★ 申請関連
use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * 勤怠登録画面（打刻画面）表示
     */
    public function index(Request $request)
    {
        $user  = Auth::user();
        $today = Carbon::today();

        // 今日の勤怠レコードを取得（あれば）
        $attendance = Attendance::with(['time', 'total', 'breaks'])
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        $status = $this->resolveStatus($attendance);

        $displayDate = $today->locale('ja')->isoFormat('YYYY年M月D日(ddd)');
        $displayTime = now()->format('H:i');

        return view('attendance', [
            'status'      => $status,
            'displayDate' => $displayDate,
            'displayTime' => $displayTime,
        ]);
    }

    /**
     * 画面用ステータス判定
     */
    protected function resolveStatus(?Attendance $attendance): string
    {
        // セッションにあればそれを優先
        $sessionStatus = session('attendance_status');
        if ($sessionStatus) {
            return $sessionStatus;
        }

        // 勤怠自体ない or 出勤していない
        if (!$attendance || !$attendance->time || !$attendance->time->start_time) {
            return 'before_work';
        }

        // 退勤済み
        if ($attendance->time->end_time) {
            return 'after_work';
        }

        // 休憩中かどうか
        $hasOpenBreak = $attendance->breaks
            ? $attendance->breaks->contains(fn($b) => empty($b->end_time))
            : false;

        return $hasOpenBreak ? 'on_break' : 'working';
    }

    /**
     * 当日の勤怠レコードを取得 or 作成
     */
    protected function getOrCreateTodayAttendanceForUser(int $userId): Attendance
    {
        return Attendance::firstOrCreate(
            [
                'user_id'   => $userId,
                'work_date' => Carbon::today()->toDateString(),
            ],
            [
                'note' => null,
            ]
        );
    }

    /**
     * 合計レコードを取得 or 作成
     */
    protected function ensureTotal(Attendance $attendance): AttendanceTotal
    {
        return $attendance->total()->firstOrCreate(
            [],
            [
                'break_minutes'      => 0,
                'total_work_minutes' => 0,
            ]
        );
    }

    /**
     * 次の休憩Noを採番
     */
    protected function nextBreakNo(Attendance $attendance): int
    {
        $maxNo = $attendance->breaks()->max('break_no');
        return ($maxNo ?? 0) + 1;
    }

    /**
     * ★ pending 申請を必ず1件付与する（無ければ作成）
     *
     * - application_statuses.code = 'pending' の id を取得
     * - 対象勤怠 + pending status のレコードが無い場合だけ新規作成
     */
    private function ensurePendingApplication(Attendance $attendance, int $userId): void
    {
        $pendingStatusId = ApplicationStatus::where('code', 'pending')->value('id');

        if (!$pendingStatusId) {
            // マスタ未設定の場合は致命的なので例外にしておく
            throw new \RuntimeException('application_statuses に pending が存在しません。Seeder を確認してください。');
        }

        $exists = AttendanceApplication::where('attendance_id', $attendance->id)
            ->where('status_id', $pendingStatusId)
            ->exists();

        if ($exists) {
            return;
        }

        AttendanceApplication::create([
            'attendance_id'     => $attendance->id,
            'applicant_user_id' => $userId,
            'status_id'         => $pendingStatusId,
            'reason'            => '自動作成（打刻により承認待ち）',
            'applied_at'        => now(),
        ]);
    }

    /**
     * 出勤ボタン
     *  - 勤怠レコードを作成/更新
     *  - 合計レコードを保証
     *  - pending 申請を自動作成（なければ）
     */
    public function clockIn(Request $request)
    {
        $user           = Auth::user();
        $attendance     = $this->getOrCreateTodayAttendanceForUser($user->id);
        $attendanceTime = $attendance->time;

        // すでに出勤済みならそのまま一覧へ
        if ($attendanceTime && $attendanceTime->start_time) {
            return redirect()->route('attendance.list');
        }

        if (!$attendanceTime) {
            $attendanceTime = new AttendanceTime();
            $attendanceTime->attendance_id = $attendance->id;
        }

        $attendanceTime->start_time = now()->format('H:i:s');
        $attendanceTime->save();

        // 合計レコードを保証
        $this->ensureTotal($attendance);

        // ★ 出勤時点で pending 申請を必ず1件付与
        $this->ensurePendingApplication($attendance, $user->id);

        session(['attendance_status' => 'working']);
        session()->forget('break_start_at');
        session()->forget('break_id');

        return redirect()->route('attendance.list');
    }

    /**
     * 退勤ボタン
     */
    public function clockOut(Request $request)
    {
        $user  = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::with(['time', 'total', 'breaks'])
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        // 出勤していなければ処理しない
        if (!$attendance || !$attendance->time || !$attendance->time->start_time) {
            return redirect()->route('attendance.list');
        }

        // すでに退勤済み
        if ($attendance->time->end_time) {
            session(['attendance_status' => 'after_work']);
            session()->forget('break_start_at');
            session()->forget('break_id');
            return redirect()->route('attendance.list');
        }

        $total = $this->ensureTotal($attendance);

        // 休憩中のまま退勤した場合の整理
        $breakId    = session('break_id');
        $breakStart = session('break_start_at');

        if ($breakId && $breakStart) {
            $break = AttendanceBreak::where('attendance_id', $attendance->id)
                ->where('id', $breakId)
                ->first();

            if ($break && empty($break->end_time)) {
                $start   = Carbon::parse($breakStart);
                $minutes = $start->diffInMinutes(now());

                $break->end_time = now()->format('H:i:s');
                $break->minutes  = $minutes;
                $break->save();

                $total->break_minutes += $minutes;
            }

            session()->forget('break_start_at');
            session()->forget('break_id');
        }

        // 退勤時刻の保存
        $attendance->time->end_time = now()->format('H:i:s');
        $attendance->time->save();

        // 勤務時間（分）を計算
        $startTime = Carbon::parse($attendance->time->start_time);
        $endTime   = Carbon::parse($attendance->time->end_time);

        $workMinutes = $startTime->diffInMinutes($endTime) - $total->break_minutes;
        if ($workMinutes < 0) {
            $workMinutes = 0;
        }

        $total->total_work_minutes = $workMinutes;
        $total->save();

        // ★ 退勤時点でも pending 申請を保証
        $this->ensurePendingApplication($attendance, $user->id);

        session(['attendance_status' => 'after_work']);

        return redirect()->route('attendance.list');
    }

    /**
     * 休憩開始ボタン
     */
    public function breakIn(Request $request)
    {
        $user  = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::with(['time', 'breaks'])
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        if (!$attendance || !$attendance->time || !$attendance->time->start_time) {
            return redirect()->route('attendance.list');
        }

        if ($attendance->time->end_time) {
            return redirect()->route('attendance.list');
        }

        // すでに休憩中なら何もしない
        if (session()->has('break_id')) {
            return redirect()->route('attendance.list');
        }

        $nextNo = $this->nextBreakNo($attendance);

        $break = AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_no'      => $nextNo,
            'start_time'    => now()->format('H:i:s'),
            'end_time'      => null,
            'minutes'       => 0,
        ]);

        session([
            'attendance_status' => 'on_break',
            'break_id'          => $break->id,
            'break_start_at'    => now()->toDateTimeString(),
        ]);

        return redirect()->route('attendance.list');
    }

    /**
     * 休憩終了ボタン
     */
    public function breakOut(Request $request)
    {
        $user  = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::with(['total', 'time'])
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        if (!$attendance || !$attendance->time || !$attendance->time->start_time) {
            return redirect()->route('attendance.list');
        }

        if ($attendance->time->end_time) {
            return redirect()->route('attendance.list');
        }

        $breakId    = session('break_id');
        $breakStart = session('break_start_at');

        if (!$breakId || !$breakStart) {
            session(['attendance_status' => 'working']);
            session()->forget('break_id');
            session()->forget('break_start_at');
            return redirect()->route('attendance.list');
        }

        $break = AttendanceBreak::where('attendance_id', $attendance->id)
            ->where('id', $breakId)
            ->first();

        if ($break) {
            $start   = Carbon::parse($breakStart);
            $minutes = $start->diffInMinutes(now());

            $break->end_time = now()->format('H:i:s');
            $break->minutes  = $minutes;
            $break->save();

            $total = $this->ensureTotal($attendance);
            $total->break_minutes += $minutes;
            $total->save();
        }

        session(['attendance_status' => 'working']);
        session()->forget('break_id');
        session()->forget('break_start_at');

        return redirect()->route('attendance.list');
    }
}
