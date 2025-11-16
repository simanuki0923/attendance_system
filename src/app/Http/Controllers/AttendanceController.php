<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceTotal;
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
        $attendance = Attendance::with(['time', 'total'])
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
     * 画面用ステータスを決定
     * - セッションを優先
     * - なければ DB の start_time / end_time から推定
     */
    protected function resolveStatus(?Attendance $attendance): string
    {
        // セッション優先
        $sessionStatus = session('attendance_status');
        if ($sessionStatus) {
            return $sessionStatus;
        }

        // DB から推定
        if (! $attendance || ! $attendance->time || ! $attendance->time->start_time) {
            // 出勤前
            return 'before_work';
        }

        if ($attendance->time->end_time) {
            // 退勤済
            return 'after_work';
        }

        // 出勤済・退勤前（休憩中かはセッションがないと分からないので working 扱い）
        return 'working';
    }

    /**
     * 本日分の勤怠レコードを取得（なければ作成）
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
     * 集計（attendance_totals）レコードを用意
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
     * 出勤ボタン
     * - 1日1回だけ出勤可能
     * - 出勤時刻を attendance_times に保存
     */
    public function clockIn(Request $request)
    {
        $user           = Auth::user();
        $attendance     = $this->getOrCreateTodayAttendanceForUser($user->id);
        $attendanceTime = $attendance->time;

        // すでに start_time が入っていれば当日出勤済み
        if ($attendanceTime && $attendanceTime->start_time) {
            return redirect()->route('attendance.list')
                ->with('error_message', '本日はすでに出勤打刻済みです。');
        }

        if (! $attendanceTime) {
            $attendanceTime = new AttendanceTime();
            $attendanceTime->attendance_id = $attendance->id;
        }

        // 時刻だけ保存（カラム型 time 想定）
        $attendanceTime->start_time = now()->format('H:i:s');
        $attendanceTime->save();

        // 集計用レコードも作成（休憩・合計勤務時間）
        $this->ensureTotal($attendance);

        // 画面ステータス更新
        session([
            'attendance_status' => 'working',
        ]);
        session()->forget('break_start_at');

        return redirect()->route('attendance.list')
            ->with('status_message', '出勤打刻を登録しました。');
    }

    /**
     * 退勤ボタン
     * - 1日1回だけ退勤可能
     * - 休憩中に押された場合は、最後の休憩を締めてから合計勤務時間を計算
     */
    public function clockOut(Request $request)
    {
        $user  = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::with(['time', 'total'])
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        if (! $attendance || ! $attendance->time || ! $attendance->time->start_time) {
            return redirect()->route('attendance.list')
                ->with('error_message', '出勤前は退勤できません。');
        }

        // すでに退勤済みなら何もしない
        if ($attendance->time->end_time) {
            session(['attendance_status' => 'after_work']);
            session()->forget('break_start_at');

            return redirect()->route('attendance.list');
        }

        $total = $this->ensureTotal($attendance);

        // もし「休憩中」のまま退勤された場合、最後の休憩も集計
        $breakStart = session('break_start_at');
        if ($breakStart) {
            $start   = Carbon::parse($breakStart);
            $minutes = $start->diffInMinutes(now());
            $total->break_minutes += $minutes;
            session()->forget('break_start_at');
        }

        // 退勤時刻を保存（こちらも時刻だけ）
        $attendance->time->end_time = now()->format('H:i:s');
        $attendance->time->save();

        // ★ 合計勤務時間（分） = 出勤〜退勤 の差 ー 休憩合計
        //   createFromFormat ではなく parse() を使うことで、フォーマット違いによる例外を防ぐ
        $startTime = Carbon::parse($attendance->time->start_time);
        $endTime   = Carbon::parse($attendance->time->end_time);

        $workMinutes = $startTime->diffInMinutes($endTime) - $total->break_minutes;
        if ($workMinutes < 0) {
            $workMinutes = 0;
        }

        $total->total_work_minutes = $workMinutes;
        $total->save();

        session([
            'attendance_status' => 'after_work',
        ]);

        return redirect()->route('attendance.list')
            ->with('status_message', '退勤打刻を登録しました。');
    }

    /**
     * 休憩入ボタン
     * - 出勤中のみ押下可能
     * - 何回でも押せるが、「休憩中」状態での連続押下は無視
     * - 休憩開始時刻はセッションに保持（DB には戻るタイミングで集計）
     */
    public function breakIn(Request $request)
    {
        $user  = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::with('time')
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        if (! $attendance || ! $attendance->time || ! $attendance->time->start_time) {
            return redirect()->route('attendance.list')
                ->with('error_message', '出勤中のみ休憩に入れます。');
        }

        if ($attendance->time->end_time) {
            return redirect()->route('attendance.list')
                ->with('error_message', '退勤後は休憩に入れません。');
        }

        // すでに休憩中なら何もしない
        if (session()->has('break_start_at')) {
            return redirect()->route('attendance.list');
        }

        session([
            'attendance_status' => 'on_break',
            'break_start_at'    => now()->toDateTimeString(),
        ]);

        return redirect()->route('attendance.list')
            ->with('status_message', '休憩に入りました。');
    }

    /**
     * 休憩戻ボタン
     * - 「休憩中」の時だけ休憩時間を集計
     * - 休憩合計（break_minutes）に加算
     */
    public function breakOut(Request $request)
    {
        $user  = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::with('total', 'time')
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        if (! $attendance || ! $attendance->time || ! $attendance->time->start_time) {
            return redirect()->route('attendance.list')
                ->with('error_message', '出勤中のみ休憩戻ができます。');
        }

        if ($attendance->time->end_time) {
            return redirect()->route('attendance.list')
                ->with('error_message', '退勤後は休憩戻できません。');
        }

        $breakStart = session('break_start_at');

        // 休憩開始が記録されていない場合は、単にステータスだけ戻す
        if (! $breakStart) {
            session(['attendance_status' => 'working']);

            return redirect()->route('attendance.list');
        }

        $total = $this->ensureTotal($attendance);

        $start   = Carbon::parse($breakStart);
        $minutes = $start->diffInMinutes(now());

        $total->break_minutes += $minutes;
        $total->save();

        session([
            'attendance_status' => 'working',
        ]);
        session()->forget('break_start_at');

        return redirect()->route('attendance.list')
            ->with('status_message', '休憩から戻りました。');
    }
}
