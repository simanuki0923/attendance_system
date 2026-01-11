<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceApplication;
use App\Models\AttendanceBreak;
use App\Models\AttendanceTime;
use App\Models\AttendanceTotal;
use App\Models\ApplicationStatus;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $today = Carbon::today();

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

    protected function resolveStatus(?Attendance $attendance): string
    {
        $sessionStatus = session('attendance_status');
        if ($sessionStatus) {
            return (string) $sessionStatus;
        }

        if (! $attendance || ! $attendance->time || ! $attendance->time->start_time) {
            return 'before_work';
        }

        if ($attendance->time->end_time) {
            return 'after_work';
        }

        $hasOpenBreak = false;

        if ($attendance->breaks) {
            $hasOpenBreak = $attendance->breaks->contains(function (AttendanceBreak $attendanceBreak): bool {
                return empty($attendanceBreak->end_time);
            });
        }

        if ($hasOpenBreak) {
            return 'on_break';
        }

        return 'working';
    }

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

    protected function nextBreakNo(Attendance $attendance): int
    {
        $maxBreakNo = $attendance->breaks()->max('break_no');

        return ((int) ($maxBreakNo ?? 0)) + 1;
    }

    private function ensurePendingApplication(Attendance $attendance, int $userId): void
    {
        $pendingStatusId = ApplicationStatus::query()
            ->where('code', ApplicationStatus::CODE_PENDING)
            ->value('id');

        if (! $pendingStatusId) {
            throw new \RuntimeException('application_statuses に pending が存在しません。Seeder を確認してください。');
        }

        $alreadyExists = AttendanceApplication::query()
            ->where('attendance_id', $attendance->id)
            ->where('status_id', $pendingStatusId)
            ->exists();

        if ($alreadyExists) {
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

    public function clockIn(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $attendance = $this->getOrCreateTodayAttendanceForUser($user->id);
        $attendanceTime = $attendance->time;

        if ($attendanceTime && $attendanceTime->start_time) {
            return redirect()->route('attendance.list');
        }

        if (! $attendanceTime) {
            $attendanceTime = new AttendanceTime();
            $attendanceTime->attendance_id = $attendance->id;
        }

        $attendanceTime->start_time = now()->format('H:i:s');
        $attendanceTime->save();

        $this->ensureTotal($attendance);
        $this->ensurePendingApplication($attendance, $user->id);

        session(['attendance_status' => 'working']);
        session()->forget('break_start_at');
        session()->forget('break_id');

        return redirect()->route('attendance.list');
    }

    public function clockOut(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::with(['time', 'total', 'breaks'])
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        if (! $attendance || ! $attendance->time || ! $attendance->time->start_time) {
            return redirect()->route('attendance.list');
        }

        if ($attendance->time->end_time) {
            session(['attendance_status' => 'after_work']);
            session()->forget('break_start_at');
            session()->forget('break_id');

            return redirect()->route('attendance.list');
        }

        $attendanceTotal = $this->ensureTotal($attendance);

        $breakId = session('break_id');
        $breakStartAt = session('break_start_at');

        if ($breakId && $breakStartAt) {
            $attendanceBreak = AttendanceBreak::query()
                ->where('attendance_id', $attendance->id)
                ->where('id', (int) $breakId)
                ->first();

            if ($attendanceBreak && empty($attendanceBreak->end_time)) {
                $breakStart = Carbon::parse((string) $breakStartAt);
                $breakMinutes = $breakStart->diffInMinutes(now());

                $attendanceBreak->end_time = now()->format('H:i:s');
                $attendanceBreak->minutes = $breakMinutes;
                $attendanceBreak->save();

                $attendanceTotal->break_minutes += $breakMinutes;
            }

            session()->forget('break_start_at');
            session()->forget('break_id');
        }

        $attendance->time->end_time = now()->format('H:i:s');
        $attendance->time->save();

        $workStart = Carbon::parse($attendance->time->start_time);
        $workEnd = Carbon::parse($attendance->time->end_time);

        $workMinutes = $workStart->diffInMinutes($workEnd) - (int) $attendanceTotal->break_minutes;
        if ($workMinutes < 0) {
            $workMinutes = 0;
        }

        $attendanceTotal->total_work_minutes = $workMinutes;
        $attendanceTotal->save();

        $this->ensurePendingApplication($attendance, $user->id);

        session(['attendance_status' => 'after_work']);

        return redirect()->route('attendance.list');
    }

    public function breakIn(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::with(['time', 'breaks'])
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        if (! $attendance || ! $attendance->time || ! $attendance->time->start_time) {
            return redirect()->route('attendance.list');
        }

        if ($attendance->time->end_time) {
            return redirect()->route('attendance.list');
        }

        if (session()->has('break_id')) {
            return redirect()->route('attendance.list');
        }

        $nextBreakNo = $this->nextBreakNo($attendance);

        $attendanceBreak = AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_no'      => $nextBreakNo,
            'start_time'    => now()->format('H:i:s'),
            'end_time'      => null,
            'minutes'       => 0,
        ]);

        session([
            'attendance_status' => 'on_break',
            'break_id'          => $attendanceBreak->id,
            'break_start_at'    => now()->toDateTimeString(),
        ]);

        return redirect()->route('attendance.list');
    }

    public function breakOut(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::with(['total', 'time'])
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        if (! $attendance || ! $attendance->time || ! $attendance->time->start_time) {
            return redirect()->route('attendance.list');
        }

        if ($attendance->time->end_time) {
            return redirect()->route('attendance.list');
        }

        $breakId = session('break_id');
        $breakStartAt = session('break_start_at');

        if (! $breakId || ! $breakStartAt) {
            session(['attendance_status' => 'working']);
            session()->forget('break_id');
            session()->forget('break_start_at');

            return redirect()->route('attendance.list');
        }

        $attendanceBreak = AttendanceBreak::query()
            ->where('attendance_id', $attendance->id)
            ->where('id', (int) $breakId)
            ->first();

        if ($attendanceBreak) {
            $breakStart = Carbon::parse((string) $breakStartAt);
            $breakMinutes = $breakStart->diffInMinutes(now());

            $attendanceBreak->end_time = now()->format('H:i:s');
            $attendanceBreak->minutes = $breakMinutes;
            $attendanceBreak->save();

            $attendanceTotal = $this->ensureTotal($attendance);
            $attendanceTotal->break_minutes += $breakMinutes;
            $attendanceTotal->save();
        }

        session(['attendance_status' => 'working']);
        session()->forget('break_id');
        session()->forget('break_start_at');

        return redirect()->route('attendance.list');
    }
}
