<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminLoginRequest;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * GET /admin/login
     */
    public function showLoginForm()
    {
        return view('admin.login');
    }

    /**
     * POST /admin/login
     */
    public function login(AdminLoginRequest $request)
    {
        $email    = (string) $request->input('email');
        $password = (string) $request->input('password');

        // ① メールでユーザー取得
        $user = User::where('email', $email)->first();

        // 仕様：入力情報が誤っている場合
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'ログイン情報が登録されていません',
            ]);
        }

        // ② パスワード照合
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'ログイン情報が登録されていません',
            ]);
        }

        // ③ 管理者判定（is_admin OR ホワイトリスト）
        if (! $this->isAdminUser($user)) {
            throw ValidationException::withMessages([
                'auth' => 'ログイン情報が登録されていません',
            ]);
        }

        // ④ ここで確定ログイン
        Auth::login($user);
        $request->session()->regenerate();

        // ★管理者ログイン後は必ず「管理者勤怠一覧」
        return redirect()->route('admin.attendance.list');
    }

    /**
     * POST /admin/logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /**
     * GET /admin/attendance/list
     * 管理者用：対象日の「全一般ユーザー」を必ず表示
     */
    public function list(Request $request)
    {
        // ?date=YYYY-MM-DD で日付切替
        $rawDate = $request->query('date');

        try {
            $targetDate = $rawDate
                ? Carbon::createFromFormat('Y-m-d', $rawDate)->startOfDay()
                : Carbon::today();
        } catch (\Throwable $e) {
            $targetDate = Carbon::today();
        }

        // 1) 一般ユーザー（管理者以外）を全員取得
        //    ★ is_admin と ホワイトリスト両方を除外して安全に
        $adminEmails = config('admin.emails', []);

        $staffUsers = User::query()
            ->where('is_admin', false)
            ->when(! empty($adminEmails), fn ($q) => $q->whereNotIn('email', $adminEmails))
            ->orderBy('id')
            ->get();

        // 2) 対象日の勤怠を user_id で引けるようまとめる
        $records = Attendance::with(['time', 'total'])
            ->whereDate('work_date', $targetDate)
            ->get()
            ->keyBy('user_id');

        // 3) 全スタッフ分の表示行を作成（勤怠無しも1行出す）
        $attendances = $staffUsers->map(function (User $u) use ($records) {

            $a     = $records->get($u->id);  // 勤怠が無ければ null
            $time  = $a?->time;
            $total = $a?->total;

            // ★勤怠がある時だけ詳細URLを作る（無い時は null）
            $detailUrl = null;
            if ($a && Route::has('attendance.detail')) {
                $detailUrl = route('attendance.detail', ['id' => $a->id]);
            }

            return [
                // Blade 互換用（勤怠が無い場合 null）
                'attendance_id' => $a?->id,

                'name_label'  => $u->name ?? '',

                'start_label' => $time?->start_time
                    ? Carbon::parse($time->start_time)->format('H:i')
                    : '',

                'end_label'   => $time?->end_time
                    ? Carbon::parse($time->end_time)->format('H:i')
                    : '',

                'break_label' => $total
                    ? $this->minutesToHhmm((int) $total->break_minutes)
                    : '',

                'total_label' => $total
                    ? $this->minutesToHhmm((int) $total->total_work_minutes)
                    : '',

                // 互換用（Bladeがこれを使わないなら残っててもOK）
                'detail_url'  => $detailUrl,
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

    // =========================
    // helper
    // =========================

    private function minutesToHhmm(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%d:%02d', $h, $m);
    }

    /**
     * ★管理者判定を統一
     * is_admin = true OR config('admin.emails') に一致
     */
    private function isAdminUser(User $user): bool
    {
        if ((bool) ($user->is_admin ?? false)) {
            return true;
        }

        return in_array($user->email, config('admin.emails', []), true);
    }
}
