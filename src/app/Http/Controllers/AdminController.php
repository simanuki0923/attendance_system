<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminLoginRequest;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function showLoginForm(): View
    {
        return view('admin.login');
    }

    public function login(AdminLoginRequest $request): RedirectResponse
    {
        $email = (string) $request->input('email');
        $password = (string) $request->input('password');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => ['ログイン情報が登録されていません'],
            ]);
        }

        if (! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['ログイン情報が登録されていません'],
            ]);
        }

        if (! $this->isAdminUser($user)) {
            throw ValidationException::withMessages([
                'email' => ['ログイン情報が登録されていません'],
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.attendance.list');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function list(Request $request): View
    {
        $rawDate = $request->query('date');

        $targetDate = $this->resolveTargetDate($rawDate);

        $prevDate = $targetDate->copy()->subDay();
        $nextDate = $targetDate->copy()->addDay();

        $prevDateUrl = route('admin.attendance.list', [
            'date' => $prevDate->toDateString(),
        ]);

        $nextDateUrl = route('admin.attendance.list', [
            'date' => $nextDate->toDateString(),
        ]);

        $adminEmails = (array) config('admin.emails', []);

        $staffUsers = User::query()
            ->when(! empty($adminEmails), function (Builder $query) use ($adminEmails): Builder {
                return $query->whereNotIn('email', $adminEmails);
            })
            ->orderBy('id')
            ->get();

        $attendancesByUserId = Attendance::query()
            ->whereDate('work_date', $targetDate->toDateString())
            ->whereIn('user_id', $staffUsers->pluck('id')->all())
            ->with(['time', 'total'])
            ->get()
            ->keyBy('user_id');

        $rows = $staffUsers->map(function (User $user) use ($attendancesByUserId): array {
            $attendance = $attendancesByUserId->get($user->id);

            $attendanceId = $attendance?->id;

            $startTime = $attendance?->time?->start_time;
            $endTime = $attendance?->time?->end_time;

            $breakMinutes = $attendance?->total?->break_minutes;
            $totalWorkMinutes = $attendance?->total?->total_work_minutes;

            $startLabel = $this->formatTimeHm($startTime);
            $endLabel = $this->formatTimeHm($endTime);

            $breakLabel = $breakMinutes === null
                ? ''
                : $this->formatMinutesToHourMinute((int) $breakMinutes);

            $totalLabel = $totalWorkMinutes === null
                ? ''
                : $this->formatMinutesToHourMinute((int) $totalWorkMinutes);

            return [
                'attendance_id' => $attendanceId,
                'is_active' => false,
                'name_label' => (string) $user->name,
                'start_label' => $startLabel,
                'end_label' => $endLabel,
                'break_label' => $breakLabel,
                'total_label' => $totalLabel,
            ];
        });

        $currentDateLabel = $targetDate->copy()->locale('ja')->isoFormat('YYYY年M月D日');
        $currentDateYmd = $targetDate->format('Y/m/d');

        return view('admin.list', [
            'currentDateLabel' => $currentDateLabel,
            'currentDateYmd' => $currentDateYmd,
            'prevDateUrl' => $prevDateUrl,
            'nextDateUrl' => $nextDateUrl,
            'attendances' => $rows,
        ]);
    }

    private function resolveTargetDate(mixed $rawDate): Carbon
    {
        if (! is_string($rawDate) || $rawDate === '') {
            return Carbon::today();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $rawDate)->startOfDay();
        } catch (\Throwable $throwable) {
            return Carbon::today();
        }
    }

    private function formatTimeHm(mixed $time): string
    {
        if (! is_string($time) || $time === '') {
            return '';
        }

        try {
            return Carbon::parse($time)->format('H:i');
        } catch (\Throwable $throwable) {
            return '';
        }
    }

    private function formatMinutesToHourMinute(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%d:%02d', $hours, $remainingMinutes);
    }

    private function isAdminUser(User $user): bool
    {
        if ((bool) ($user->is_admin ?? false)) {
            return true;
        }

        return in_array((string) $user->email, (array) config('admin.emails', []), true);
    }
}
