<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceListController;
use App\Http\Controllers\DetailtController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\AdminRequestController;
use App\Http\Controllers\EditController;

// ======================================================
// ★管理者判定（is_admin / ホワイトリスト両対応）
// ======================================================
$isAdminUser = function ($user): bool {
    if (! $user) return false;

    // DB の is_admin を優先
    if ((bool) ($user->is_admin ?? false)) {
        return true;
    }

    // ホワイトリスト（config/admin.php の emails）
    return in_array($user->email, config('admin.emails', []), true);
};

// ======================================================
// トップはログインへ
// ======================================================
Route::get('/', function () {
    return redirect()->route('login');
});

// ======================================================
// 管理者ログイン（未認証OK）
// ======================================================
Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminController::class, 'showLoginForm'])
        ->name('admin.login');

    Route::post('/login', [AdminController::class, 'login'])
        ->name('admin.login.post');

    Route::post('/logout', [AdminController::class, 'logout'])
        ->name('admin.logout');
});

// ======================================================
// ★追加：勤怠詳細（日付指定・一般ユーザー用）
//  - GET /attendance/detail/date/{date}
//    打刻レコードが無ければ自動作成して詳細画面へ
// ======================================================
Route::middleware(['auth'])->get(
    '/attendance/detail/date/{date}',
    function (Request $request, $date) use ($isAdminUser) {

        $user    = $request->user();
        $isAdmin = $isAdminUser($user);

        // 管理者はこのURLは使わない想定なので 404
        if ($isAdmin) {
            abort(404);
        }

        // 一般ユーザーはメール認証必須
        if (! $user || ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        // 実処理は DetailtController@showByDate へ
        return app(DetailtController::class)->showByDate($date);
    }
)->name('attendance.detail.byDate');

// ======================================================
// 勤怠詳細（一般 / 管理者 共通パス）
//  - GET /attendance/detail/{id}
// ======================================================
Route::middleware(['auth'])->get(
    '/attendance/detail/{id}',
    function (Request $request, $id) use ($isAdminUser) {

        $user    = $request->user();
        $isAdmin = $isAdminUser($user);

        // 一般ユーザーだけメール認証必須
        if (! $isAdmin) {
            if (! $user || ! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            return app(DetailtController::class)->show($id);
        }

        // 管理者詳細（編集画面）
        return app(EditController::class)->show($id);
    }
)->name('attendance.detail');

// ======================================================
// 勤怠詳細 更新（一般 / 管理者 共通パス）
//  - PATCH /attendance/detail/{id}
// ======================================================
Route::middleware(['auth'])->patch(
    '/attendance/detail/{id}',
    function (Request $request, $id) use ($isAdminUser) {

        $user    = $request->user();
        $isAdmin = $isAdminUser($user);

        // 一般ユーザーだけメール認証必須
        if (! $isAdmin) {
            if (! $user || ! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            // ★修正ポイント：
            // FormRequest を DetailtController に注入できるように
            // 「インスタンス + メソッド」で app()->call する
            return app()->call(
                [app(DetailtController::class), 'update'],
                ['id' => $id]
            );
        }

        // ★管理者側も同様に統一（将来 FormRequest 化しても安全）
        return app()->call(
            [app(EditController::class), 'update'],
            ['id' => $id]
        );
    }
)->name('attendance.detail.update');

// ======================================================
// 申請一覧（一般 / 管理者 共通パス）
//  - GET /stamp_correction_request/list
// ======================================================
Route::middleware(['auth'])->get(
    '/stamp_correction_request/list',
    function (Request $request) use ($isAdminUser) {

        $user    = $request->user();
        $isAdmin = $isAdminUser($user);

        // 一般ユーザーだけメール認証必須
        if (! $isAdmin) {
            if (! $user || ! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }
        }

        return $isAdmin
            ? app(AdminRequestController::class)->index($request)
            : app(RequestController::class)->index($request);
    }
)->name('requests.list');

// ======================================================
// 修正申請 承認画面（管理者専用）
//  - GET  /stamp_correction_request/approve/{attendance_correct_request_id}
//     → admin/detail.blade.php（承認ボタン付き）
//  - POST /stamp_correction_request/approve/{attendance_correct_request_id}
//     → 承認処理
// ======================================================
Route::middleware(['auth', 'admin'])->group(function () {

    Route::get(
        '/stamp_correction_request/approve/{attendance_correct_request_id}',
        [AdminRequestController::class, 'showApprove']
    )->name('stamp_correction_request.approve.show');

    Route::post(
        '/stamp_correction_request/approve/{attendance_correct_request_id}',
        [AdminRequestController::class, 'approve']
    )->name('stamp_correction_request.approve');
});

// ======================================================
// 一般ユーザー（verified 必須）
// ======================================================
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/attendance', [AttendanceController::class, 'index'])
        ->name('attendance.list');

    Route::get('/attendance/list', [AttendanceListController::class, 'index'])
        ->name('attendance.userList');

    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])
        ->name('attendance.clockIn');

    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])
        ->name('attendance.clockOut');

    Route::post('/attendance/break-in', [AttendanceController::class, 'breakIn'])
        ->name('attendance.breakIn');

    Route::post('/attendance/break-out', [AttendanceController::class, 'breakOut'])
        ->name('attendance.breakOut');
});

// ======================================================
// 管理者ルート（auth + admin）
// ======================================================
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->group(function () {

        Route::get('/attendance/list', [AdminController::class, 'list'])
            ->name('admin.attendance.list');

        Route::get('/staff/list', [StaffController::class, 'index'])
            ->name('admin.staff.list');

        Route::get('/staff/{user}/attendance', [StaffController::class, 'attendance'])
            ->whereNumber('user')
            ->name('admin.staff.attendance');

        // ★追加：スタッフ別 月次勤怠一覧 CSV ダウンロード
        // 例）GET /admin/staff/3/attendance/csv?month=2025-11
        Route::get('/staff/{user}/attendance/csv', [StaffController::class, 'attendanceCsv'])
            ->whereNumber('user')
            ->name('admin.staff.attendance.csv');
    });

// ======================================================
// home（管理者は admin 側へ）
// ======================================================
Route::get('/home', function () use ($isAdminUser) {

    $user = auth()->user();

    if ($isAdminUser($user)) {
        return redirect()->route('admin.attendance.list');
    }

    return redirect()->route('attendance.list');
})->name('home');
