<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminLoginRequest;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.login');
    }

    public function login(AdminLoginRequest $request)
    {
        $email    = (string) $request->input('email');
        $password = (string) $request->input('password');

        $user = User::where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'ログイン情報が登録されていません',
            ]);
        }

        if (! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'ログイン情報が登録されていません',
            ]);
        }

        if (! $this->isAdminUser($user)) {
            throw ValidationException::withMessages([
                'email' => 'ログイン情報が登録されていません',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.attendance.list');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function list(Request $request)
    {
        $rawDate = $request->query('date');

        try {
            $targetDate = $rawDate
                ? Carbon::createFromFormat('Y-m-d', (string) $rawDate)->startOfDay()
                : Carbon::today();
        } catch (\Throwable $e) {
            $targetDate = Carbon::today();
        }

        $adminEmails = config('admin.emails', []);

        $staffUsers = User::query()
            ->where('is_admin', false)
            ->when(! empty($adminEmails), fn ($q) => $q->whereNotIn('email', $adminEmails))
            ->orderBy('id')
            ->get();

        $records = Attendance::with(['time', 'total'])
            ->whereDate('work_date', $targetDate->toDateString())
            ->get()
            ->keyBy('user_id');

        $attendances = $staffUsers->map(function (User $u) use ($records) {
            $a     = $records->get($u->id);
            $time  = $a?->time;
            $total = $a?->total;

            $detailUrl = $a ? route('admin.attendance.detail', ['id' => $a->id]) : null;

            return [
                'attendance_id' => $a?->id,
                'name_label'    => $u->name ?? '',
                'start_label'   => $time?->start_time ? Carbon::parse($time->start_time)->format('H:i') : '',
                'end_label'     => $time?->end_time ? Carbon::parse($time->end_time)->format('H:i') : '',
                'break_label'   => $total ? $this->minutesToHhmm((int) $total->break_minutes) : '',
                'total_label'   => $total ? $this->minutesToHhmm((int) $total->total_work_minutes) : '',
                'detail_url'    => $detailUrl,
            ];
        });

        $currentDateLabel = $targetDate->copy()
            ->locale('ja')
            ->isoFormat('YYYY年M月D日(ddd)');

        $currentDateYmd = $targetDate->format('Y/m/d');

        $prevDateUrl = route('admin.attendance.list', [
            'date' => $targetDate->copy()->subDay()->format('Y-m-d'),
        ]);

        $nextDateUrl = route('admin.attendance.list', [
            'date' => $targetDate->copy()->addDay()->format('Y-m-d'),
        ]);

        return view('admin.list', compact(
            'currentDateLabel',
            'currentDateYmd',
            'attendances',
            'prevDateUrl',
            'nextDateUrl'
        ));
    }

    private function minutesToHhmm(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return sprintf('%d:%02d', $h, $m);
    }

    private function isAdminUser(User $user): bool
    {
        if ((bool) ($user->is_admin ?? false)) {
            return true;
        }

        return in_array($user->email, config('admin.emails', []), true);
    }
}
