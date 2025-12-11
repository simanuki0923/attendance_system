<?php

namespace Tests\Feature;

use App\Models\ApplicationStatus;
use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceClockOutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * pending ステータスだけテスト用に投入しておく
     * （AttendanceController::ensurePendingApplication() 対応）.
     */
    protected function setUp(): void
    {
        parent::setUp();

        ApplicationStatus::firstOrCreate(
            ['code' => 'pending'],
            [
                'label'   => '承認待ち',
                'sort_no' => 1,
            ]
        );
    }

    /**
     * テスト1:
     * 退勤ボタンが正しく機能する
     *
     * シナリオ:
     * - ステータスが「勤務中」のユーザーでログイン
     * - 勤怠画面に「退勤」ボタンが表示されている
     * - 退勤処理後、画面上のステータスが「退勤済」になる
     */
    public function test_clock_out_button_works_and_status_changes_to_after_work(): void
    {
        // 今日の日付 & 退勤時刻を固定（18:00）
        Carbon::setTestNow(Carbon::create(2025, 12, 10, 18, 0, 0));

        // メール認証済みユーザー作成（verified ミドルウェア対応）
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // その日の勤怠レコードを作成（勤務中：出勤済み・退勤前）
        $attendance = Attendance::create([
            'user_id'   => $user->id,
            'work_date' => Carbon::today()->toDateString(),
            'note'      => null,
        ]);

        AttendanceTime::create([
            'attendance_id' => $attendance->id,
            'start_time'    => '09:00:00',
            'end_time'      => null,
        ]);

        $this->actingAs($user);

        // 勤怠画面で「退勤」ボタンが表示されていること
        $response = $this->get(route('attendance.list'));
        $response->assertStatus(200);
        $response->assertSee('退勤'); // ボタン文言

        // 退勤打刻
        $response = $this->post(route('attendance.clockOut'));
        $response->assertRedirect(route('attendance.list'));

        // 退勤後、勤怠画面でステータスが「退勤済」になっていること
        $response = $this->get(route('attendance.list'));
        $response->assertStatus(200);
        $response->assertSee('退勤済');

        // DB にも退勤時刻が保存されていること
        $this->assertDatabaseHas('attendance_times', [
            'attendance_id' => $attendance->id,
            'end_time'      => '18:00:00',
        ]);
    }

    /**
     * テスト2:
     * 退勤時刻が勤怠一覧画面で確認できる
     *
     * シナリオ:
     * - ステータスが「勤務外」のユーザーでログイン（＝事前の勤怠レコードなし）
     * - 出勤 → 退勤の順で打刻
     * - 勤怠一覧画面で退勤時刻が確認できる
     */
    public function test_clock_out_time_is_visible_on_attendance_list(): void
    {
        // メール認証済みユーザー
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        // まず 9:00 に出勤
        Carbon::setTestNow(Carbon::create(2025, 12, 10, 9, 0, 0));
        $this->post(route('attendance.clockIn'))
            ->assertRedirect(route('attendance.list'));

        // つづいて 18:00 に退勤
        Carbon::setTestNow(Carbon::create(2025, 12, 10, 18, 0, 0));
        $this->post(route('attendance.clockOut'))
            ->assertRedirect(route('attendance.list'));

        // 勤怠一覧画面で退勤時刻が表示されていること
        $response = $this->get(route('attendance.userList'));
        $response->assertStatus(200);

        // 画面上に「18:00」が表示されていることを確認
        // （18:00:00 のような表示でも部分一致で通る想定）
        $response->assertSee('18:00');

        // DB 側でも退勤時刻が正しく保存されていること
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', Carbon::create(2025, 12, 10)->toDateString())
            ->first();

        $this->assertNotNull($attendance, '当日の勤怠レコードが作成されていること');

        $this->assertDatabaseHas('attendance_times', [
            'attendance_id' => $attendance->id,
            'end_time'      => '18:00:00',
        ]);
    }
}
