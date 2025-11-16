<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;

// トップはログイン画面へ飛ばす
Route::get('/', function () {
    return redirect()->route('login');
});

// 認証済 & メール認証済のみ利用できるルート
Route::middleware(['auth', 'verified'])->group(function () {
    // 勤怠登録画面（GET）
    Route::get('/attendance', [AttendanceController::class, 'index'])
        ->name('attendance.list');
    
    // ★ 申請一覧画面（今回エラーになっているやつ）
    Route::get('/requests', [RequestController::class, 'index'])
        ->name('requests.list');   // ← エラーメッセージと同じ「requests.list」にする

    // ★ スタッフ一覧画面（あれば）
    Route::get('/staff', [StaffController::class, 'index'])
        ->name('staff.list');

    // 出勤
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])
        ->name('attendance.clockIn');

    // 退勤
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])
        ->name('attendance.clockOut');

    // 休憩入
    Route::post('/attendance/break-in', [AttendanceController::class, 'breakIn'])
        ->name('attendance.breakIn');

    // 休憩戻
    Route::post('/attendance/break-out', [AttendanceController::class, 'breakOut'])
        ->name('attendance.breakOut');
});

// home ルート（welcome.blade.php などから参照される想定）
Route::get('/home', function () {
    return redirect()->route('attendance.index');
})->name('home');
