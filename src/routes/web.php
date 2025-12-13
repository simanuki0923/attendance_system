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
// ★勤怠詳細（日付指定・一般ユーザー用）
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
//  - POST /stamp_correction_request/approve/{attendance_correct_request_id}
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

    // ✅ 一般ユーザーの勤怠詳細（テキスト通り）
    Route::get('/attendance/detail/{id}', [DetailtController::class, 'show'])
        ->whereNumber('id')
        ->name('attendance.detail');

    // ✅ 一般ユーザーの勤怠詳細 更新（FormRequest を安全に注入するため app()->call）
    Route::patch('/attendance/detail/{id}', function (Request $request, int $id) {
        return app()->call(
            [app(DetailtController::class), 'update'],
            ['id' => $id]
        );
    })
        ->whereNumber('id')
        ->name('attendance.detail.update');

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

        // 勤怠一覧（管理者）
        Route::get('/attendance/list', [AdminController::class, 'list'])
            ->name('admin.attendance.list');

        // ✅ 勤怠詳細（管理者）テキスト通り：/admin/attendance/{id}
        Route::get('/attendance/{id}', [EditController::class, 'show'])
            ->whereNumber('id')
            ->name('admin.attendance.detail');

        // ✅ 勤怠詳細 更新（管理者）テキスト通り：PATCH /admin/attendance/{id}
        Route::patch('/attendance/{id}', function (Request $request, int $id) {
            return app()->call(
                [app(EditController::class), 'update'],
                ['id' => $id]
            );
        })
            ->whereNumber('id')
            ->name('admin.attendance.detail.update');

        // スタッフ一覧（管理者）
        Route::get('/staff/list', [StaffController::class, 'index'])
            ->name('admin.staff.list');

        // ✅ スタッフ別勤怠一覧（管理者）テキスト通り：/admin/attendance/staff/{id}
        Route::get('/attendance/staff/{id}', [StaffController::class, 'attendance'])
            ->whereNumber('id')
            ->name('admin.attendance.staff');

        // 追加：CSV（新URL側に寄せる）
        Route::get('/attendance/staff/{id}/csv', [StaffController::class, 'attendanceCsv'])
            ->whereNumber('id')
            ->name('admin.attendance.staff.csv');

        // ------------------------------------------------------
        // 互換（旧URL → 新URLへリダイレクト）
        // ------------------------------------------------------
        Route::get('/staff/{user}/attendance', function (Request $request, int $user) {
            return redirect()->route('admin.attendance.staff', array_merge(
                ['id' => $user],
                $request->only('month')
            ));
        })->whereNumber('user');

        Route::get('/staff/{user}/attendance/csv', function (Request $request, int $user) {
            return redirect()->route('admin.attendance.staff.csv', array_merge(
                ['id' => $user],
                $request->only('month')
            ));
        })->whereNumber('user');
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
