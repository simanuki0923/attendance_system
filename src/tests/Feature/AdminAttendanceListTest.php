<?php

namespace Tests\Feature;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceTotal;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        $admin = User::factory()->create([
            'name' => '管理者 太郎',
            'email' => 'admin@example.com',
        ]);

        // User::$fillable に is_admin が無い前提なので forceFill で確実に管理者化する
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function createStaffUser(string $name, string $email): User
    {
        $staff = User::factory()->create([
            'name' => $name,
            'email' => $email,
        ]);

        // 念のため（デフォルト false の想定）
        $staff->forceFill(['is_admin' => false])->save();

        return $staff;
    }

    private function createAttendance(User $user, Carbon $date, ?string $start, ?string $end, int $breakMinutes, int $totalMinutes): Attendance
    {
        $attendance = Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $date->toDateString(),
            'note'      => null,
        ]);

        AttendanceTime::create([
            'attendance_id' => $attendance->id,
            'start_time'    => $start, // '09:00:00' など（time型想定）
            'end_time'      => $end,
        ]);

        AttendanceTotal::create([
            'attendance_id'      => $attendance->id,
            'break_minutes'      => $breakMinutes,
            'total_work_minutes' => $totalMinutes,
        ]);

        return $attendance;
    }

    /**
     * ◆テスト内容
     * その日になされた全ユーザーの勤怠情報が正確に確認できる
     */
    public function testAdminCanSeeAllStaffAttendanceForTargetDate(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 13, 10, 0, 0, 'Asia/Tokyo'));

        $admin = $this->createAdminUser();
        $staffA = $this->createStaffUser('一般 一郎', 'staff1@example.com');
        $staffB = $this->createStaffUser('一般 二郎', 'staff2@example.com');

        $target = Carbon::today();

        // staffA は勤怠あり
        $this->createAttendance($staffA, $target, '09:00:00', '18:00:00', 60, 480); // 休憩1:00 / 合計8:00

        // staffB は勤怠なし（一覧に「空で1行」は出る仕様）

        $res = $this->actingAs($admin)->get(route('admin.attendance.list'));

        $res->assertStatus(200);

        // staffA の表示確認
        $res->assertSeeText('一般 一郎');
        $res->assertSeeText('09:00');
        $res->assertSeeText('18:00');
        $res->assertSeeText('1:00'); // break_label
        $res->assertSeeText('8:00'); // total_label

        // staffB の表示確認（名前は出るが、時刻や集計は空）
        $res->assertSeeText('一般 二郎');

        // ナビ文言がある（前日/翌日）
        $res->assertSeeText('前日');
        $res->assertSeeText('翌日');
    }

    /**
     * ◆テスト内容
     * 遷移した際に現在の日付が表示される
     */
    public function testAdminListShowsCurrentDateOnArrival(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 13, 10, 0, 0, 'Asia/Tokyo'));

        $admin = $this->createAdminUser();

        $res = $this->actingAs($admin)->get(route('admin.attendance.list'));

        $res->assertStatus(200);

        // blade では currentDateYmd が "Y/m/d" で中央表示される
        $res->assertSeeText('2025/12/13');

        // タイトルにも "YYYY年M月D日" が出る（曜日表記は環境差が出るので日付部分だけ見る）
        $res->assertSee('2025年12月13日');
    }

    /**
     * ◆テスト内容
     * 「前日」を押下した時に前の日の勤怠情報が表示される
     */
    public function testAdminCanNavigateToPreviousDay(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 13, 10, 0, 0, 'Asia/Tokyo'));

        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser('一般 一郎', 'staff1@example.com');

        $today = Carbon::today();
        $yesterday = Carbon::today()->subDay();

        // 前日だけ勤怠を入れておく
        $this->createAttendance($staff, $yesterday, '10:00:00', '19:00:00', 45, 495); // 0:45 / 8:15

        // 前日URLへアクセス（=「前日」押下相当）
        $res = $this->actingAs($admin)->get(route('admin.attendance.list', [
            'date' => $yesterday->format('Y-m-d'),
        ]));

        $res->assertStatus(200);

        // 日付が前日になっている
        $res->assertSeeText($yesterday->format('Y/m/d'));

        // 前日の勤怠が表示される
        $res->assertSeeText('一般 一郎');
        $res->assertSeeText('10:00');
        $res->assertSeeText('19:00');
        $res->assertSeeText('0:45');
        $res->assertSeeText('8:15');

        // 今日の情報が「必須で出る」わけではないが、混ざってないことを軽く確認
        $res->assertDontSee($today->format('Y/m/d'));
    }

    /**
     * ◆テスト内容
     * 「翌日」を押下した時に次の日の勤怠情報が表示される
     */
    public function testAdminCanNavigateToNextDay(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 13, 10, 0, 0, 'Asia/Tokyo'));

        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser('一般 一郎', 'staff1@example.com');

        $tomorrow = Carbon::today()->addDay();

        // 翌日だけ勤怠を入れておく
        $this->createAttendance($staff, $tomorrow, '08:30:00', '17:30:00', 60, 480); // 1:00 / 8:00

        // 翌日URLへアクセス（=「翌日」押下相当）
        $res = $this->actingAs($admin)->get(route('admin.attendance.list', [
            'date' => $tomorrow->format('Y-m-d'),
        ]));

        $res->assertStatus(200);

        // 日付が翌日になっている
        $res->assertSeeText($tomorrow->format('Y/m/d'));

        // 翌日の勤怠が表示される
        $res->assertSeeText('一般 一郎');
        $res->assertSeeText('08:30');
        $res->assertSeeText('17:30');
        $res->assertSeeText('1:00');
        $res->assertSeeText('8:00');
    }
}
