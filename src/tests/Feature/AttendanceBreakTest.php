<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceBreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class AttendanceBreakTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 出勤中ユーザーに「休憩入」ボタンが表示され、
     * 休憩入処理後にステータスが「休憩中」になる
     */
    public function test_break_in_button_works_and_status_changes_to_on_break(): void
    {
        Carbon::setTestNow('2025-12-10 10:00:00');

        // 出勤中ユーザーと当日勤怠を準備
        [$user, $attendance] = $this->createWorkingUserWithAttendance();

        // 画面に「休憩入」ボタンが表示されていること
        $response = $this->actingAs($user)->get(route('attendance.list'));
        $response
            ->assertStatus(200)
            ->assertSee('休憩入');   // attendance.blade.php 上のボタン文言

        // 休憩入処理
        $response = $this->actingAs($user)->post(route('attendance.breakIn'));
        $response->assertRedirect(route('attendance.list'));

        // セッション上のステータスが on_break に変わっていること
        $this->assertEquals('on_break', session('attendance_status'));

        // DB に休憩レコードが作成されていること
        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_no'      => 1,
        ]);

        // 画面側でも「休憩中」バッジと「休憩戻」ボタンが表示されること
        $response = $this->actingAs($user)->get(route('attendance.list'));
        $response
            ->assertStatus(200)
            ->assertSee('休憩中')
            ->assertSee('休憩戻')
            ->assertDontSee('休憩入');
    }

    /**
     * 休憩は 1 日に何回でもできる（休憩入→休憩戻 後も
     * 再度「休憩入」ボタンが表示される）
     */
    public function test_break_can_be_taken_multiple_times_in_a_day(): void
    {
        Carbon::setTestNow('2025-12-10 10:00:00');

        [$user, $attendance] = $this->createWorkingUserWithAttendance();

        // 1 回目 休憩入
        $this->actingAs($user)
            ->post(route('attendance.breakIn'))
            ->assertRedirect(route('attendance.list'));

        // 1 回目 休憩戻
        Carbon::setTestNow('2025-12-10 10:30:00');

        $this->actingAs($user)
            ->post(route('attendance.breakOut'))
            ->assertRedirect(route('attendance.list'));

        // 再度画面を開いたときに「休憩入」ボタンが表示されること
        $response = $this->actingAs($user)->get(route('attendance.list'));
        $response
            ->assertStatus(200)
            ->assertSee('出勤中')
            ->assertSee('休憩入');
    }

    /**
     * 休憩戻ボタンが正しく機能し、ステータスが「出勤中」に戻る
     */
    public function test_break_out_button_works_and_status_changes_back_to_working(): void
    {
        Carbon::setTestNow('2025-12-10 10:00:00');

        [$user, $attendance] = $this->createWorkingUserWithAttendance();

        // まず休憩入
        $this->actingAs($user)
            ->post(route('attendance.breakIn'))
            ->assertRedirect(route('attendance.list'));

        // 休憩中になっていることを確認
        $this->assertEquals('on_break', session('attendance_status'));

        // 少し時間を進めて休憩戻
        Carbon::setTestNow('2025-12-10 10:30:00');

        $this->actingAs($user)
            ->post(route('attendance.breakOut'))
            ->assertRedirect(route('attendance.list'));

        // ステータスが「出勤中」に戻っていること
        $this->assertEquals('working', session('attendance_status'));

        // 対象休憩レコードに end_time が入り、minutes が 0 以上であること
        $break = AttendanceBreak::where('attendance_id', $attendance->id)
            ->orderBy('break_no')
            ->first();

        $this->assertNotNull($break);
        $this->assertNotNull($break->end_time);
        $this->assertGreaterThanOrEqual(0, $break->minutes);
    }

    /**
     * 休憩戻も 1 日に何回でもできる
     * （2 回目の休憩中には「休憩戻」ボタンが表示される）
     */
    public function test_break_out_can_be_done_multiple_times_in_a_day(): void
    {
        Carbon::setTestNow('2025-12-10 10:00:00');

        [$user, $attendance] = $this->createWorkingUserWithAttendance();

        // 1 回目 休憩入 → 休憩戻
        $this->actingAs($user)
            ->post(route('attendance.breakIn'))
            ->assertRedirect(route('attendance.list'));

        Carbon::setTestNow('2025-12-10 10:30:00');

        $this->actingAs($user)
            ->post(route('attendance.breakOut'))
            ->assertRedirect(route('attendance.list'));

        // 2 回目 休憩入
        Carbon::setTestNow('2025-12-10 11:00:00');

        $this->actingAs($user)
            ->post(route('attendance.breakIn'))
            ->assertRedirect(route('attendance.list'));

        // 2 回目休憩中の画面で「休憩戻」ボタンが表示されること
        $response = $this->actingAs($user)->get(route('attendance.list'));
        $response
            ->assertStatus(200)
            ->assertSee('休憩中')
            ->assertSee('休憩戻');
    }

    /**
     * 休憩時刻が正確に記録されていることを確認
     * （勤怠一覧画面で利用される元データとして DB の休憩時刻を検証）
     */
    public function test_break_times_are_recorded_correctly_in_database(): void
    {
        // 休憩入 12:00
        Carbon::setTestNow('2025-12-10 12:00:00');

        [$user, $attendance] = $this->createWorkingUserWithAttendance();

        $this->actingAs($user)
            ->post(route('attendance.breakIn'))
            ->assertRedirect(route('attendance.list'));

        // 休憩戻 12:30
        Carbon::setTestNow('2025-12-10 12:30:00');

        $this->actingAs($user)
            ->post(route('attendance.breakOut'))
            ->assertRedirect(route('attendance.list'));

        // DB に休憩開始／終了時刻が正しく入っていること
        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_no'      => 1,
            'start_time'    => '12:00:00',
            'end_time'      => '12:30:00',
            'minutes'       => 30,
        ]);
    }

    /**
     * 出勤中状態のユーザーと当日勤怠レコードを作るヘルパー
     *
     * @return array{0: \App\Models\User, 1: \App\Models\Attendance}
     */
    private function createWorkingUserWithAttendance(): array
    {
        // メール認証済みユーザー
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // 当日の勤怠（日付は date 型に合わせて toDateString）
        $attendance = Attendance::create([
            'user_id'   => $user->id,
            'work_date' => Carbon::today()->toDateString(),
            'note'      => null,
        ]);

        // 出勤中（start_time のみセット）
        AttendanceTime::create([
            'attendance_id' => $attendance->id,
            'start_time'    => '09:00:00',
            'end_time'      => null,
        ]);

        return [$user, $attendance];
    }
}
