<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminRequestController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceListController;
use App\Http\Controllers\DetailController;
use App\Http\Controllers\EditController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\StaffController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminController::class, 'showLoginForm'])->name('admin.login');
        Route::post('/login', [AdminController::class, 'login'])->name('admin.login.post');
    });

    Route::post('/logout', [AdminController::class, 'logout'])
        ->middleware('auth')
        ->name('admin.logout');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/attendance/list', [AdminController::class, 'list'])->name('admin.attendance.list');

        Route::get('/attendance/{id}', [EditController::class, 'show'])
            ->whereNumber('id')
            ->name('admin.attendance.detail');

        Route::patch('/attendance/{id}', [EditController::class, 'update'])
            ->whereNumber('id')
            ->name('admin.attendance.detail.update');

        Route::get('/staff/list', [StaffController::class, 'index'])->name('admin.staff.list');

        Route::get('/attendance/staff/{id}', [StaffController::class, 'attendance'])
            ->whereNumber('id')
            ->name('admin.attendance.staff');

        Route::get('/attendance/staff/{id}/csv', [StaffController::class, 'attendanceCsv'])
            ->whereNumber('id')
            ->name('admin.attendance.staff.csv');
    });
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminRequestController::class, 'showApprove'])
        ->name('stamp_correction_request.approve.show');

    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminRequestController::class, 'approve'])
        ->name('stamp_correction_request.approve');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.list');

    Route::get('/attendance/list', [AttendanceListController::class, 'index'])->name('attendance.userList');

    Route::get('/attendance/detail/{id}', [DetailController::class, 'show'])
        ->whereNumber('id')
        ->name('attendance.detail');

    Route::patch('/attendance/detail/{id}', [DetailController::class, 'update'])
        ->whereNumber('id')
        ->name('attendance.detail.update');

    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clockIn');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clockOut');
    Route::post('/attendance/break-in', [AttendanceController::class, 'breakIn'])->name('attendance.breakIn');
    Route::post('/attendance/break-out', [AttendanceController::class, 'breakOut'])->name('attendance.breakOut');
});

Route::middleware('auth')->get('/stamp_correction_request/list', static function (Request $request) {
    $user = $request->user();

    $isAdmin = (bool) ($user->is_admin ?? false) || in_array($user->email, config('admin.emails', []), true);

    if (! $isAdmin && ! $user?->hasVerifiedEmail()) {
        return redirect()->route('verification.notice');
    }

    return $isAdmin
        ? app(AdminRequestController::class)->index($request)
        : app(RequestController::class)->index($request);
})->name('requests.list');