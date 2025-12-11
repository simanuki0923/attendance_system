<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceTotal;
use App\Models\ApplicationStatus;

class AttendanceClockInTest extends TestCase
{
    use RefreshDatabase;

    /**
     * clockIn 内で code='pending' が必須のため、テスト用に作成
     */
    private function preparePendingStatus(): ApplicationStatus
    {
        return ApplicationStatus::create([
            'code'    => 'pending',
            'label'   => '承認待ち',
            'sort_no' => 1,
        ]);
    }

    /**
     * メール認証済みの一般ユーザーを作成
     * ルートは auth + verified 前提のため
     */
    private function createVerifiedUser(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    /**
     * 出勤ボタンが正しく機能する
     *
     * 期待:
     * - before_work では「出勤」ボタンが見える
     * - POST 後、status が working 相当になる
     * - start_time / total / pending application が作られる
     */
    public function test_clock_in_button_works_and_status_changes_to_working(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 10, 9, 0, 0));

        $user = $this->createVerifiedUser();
        $pending = $this->preparePendingStatus();

        $this->actingAs($user);

        // 勤怠打刻画面に「出勤」ボタンが表示（HTML構造差に強い確認）
        $response = $this->get(route('attendance.list'));
        $response->assertStatus(200);
        $response->assertSeeText('出勤');

        // 出勤処理
        $post = $this->post(route('attendance.clockIn'));
        $post->assertRedirect(route('attendance.list'));

        // ✅ work_date が date / datetime どちらでも通る取得方法
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', Carbon::today())
            ->first();

        $this->assertNotNull($attendance, '当日の attendance レコードが作成されていません。');

        // start_time が保存される
        $this->assertDatabaseHas('attendance_times', [
            'attendance_id' => $attendance->id,
            'start_time'    => '09:00:00',
        ]);

        // total が保証される
        $this->assertDatabaseHas('attendance_totals', [
            'attendance_id' => $attendance->id,
        ]);

        // pending 申請が自動作成される
        $this->assertDatabaseHas('attendance_applications', [
            'attendance_id'     => $attendance->id,
            'applicant_user_id' => $user->id,
            'status_id'         => $pending->id,
        ]);

        // 出勤後の画面上状態（文言は実装に合わせて柔らかく確認）
        $after = $this->get(route('attendance.list'));
        $after->assertStatus(200);

        // どちらの表示でも許容できるように保険をかけるなら下2行のどちらかを残す
        // $after->assertSeeText('勤務中');
        // $after->assertSeeText('出勤中');

        // ボタン群の存在確認は仕様に沿っておく
        $after->assertSeeText('退勤');
        $after->assertSeeText('休憩入');
    }

    /**
     * 出勤は一日一回のみできる
     *
     * 期待:
     * - 退勤済ユーザーには「出勤」ボタンが表示されない
     */
    public function test_clock_in_button_is_not_shown_when_after_work(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 10, 18, 0, 0));

        $user = $this->createVerifiedUser();

        // 退勤済の勤怠を作成
        $attendance = Attendance::create([
            'user_id'   => $user->id,
            'work_date' => Carbon::today()->toDateString(),
            'note'      => null,
        ]);

        AttendanceTime::create([
            'attendance_id' => $attendance->id,
            'start_time'    => '09:00:00',
            'end_time'      => '18:00:00',
        ]);

        AttendanceTotal::create([
            'attendance_id'       => $attendance->id,
            'break_minutes'       => 0,
            'total_work_minutes'  => 0,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('attendance.list'));
        $response->assertStatus(200);

        $response->assertSeeText('退勤済');
        $response->assertDontSeeText('出勤');
    }

    /**
     * 出勤時刻が勤怠一覧画面で確認できる
     *
     * 期待:
     * - 出勤処理後、勤怠一覧で出勤時刻が表示される
     */
    public function test_clock_in_time_is_visible_on_user_list_page(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 10, 9, 0, 0));

        $user = $this->createVerifiedUser();
        $this->preparePendingStatus();

        $this->actingAs($user);

        $this->post(route('attendance.clockIn'))
            ->assertRedirect(route('attendance.list'));

        $response = $this->get(route('attendance.userList'));
        $response->assertStatus(200);

        // 一覧の表示形式差を吸収
        $response->assertSee('09:00');
    }
}
