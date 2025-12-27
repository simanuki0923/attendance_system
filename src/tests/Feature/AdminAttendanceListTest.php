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

        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function createStaffUser(string $name, string $email): User
    {
        $staff = User::factory()->create([
            'name' => $name,
            'email' => $email,
        ]);

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
            'start_time'    => $start,
            'end_time'      => $end,
        ]);

        AttendanceTotal::create([
            'attendance_id'      => $attendance->id,
            'break_minutes'      => $breakMinutes,
            'total_work_minutes' => $totalMinutes,
        ]);

        return $attendance;
    }

    public function testAdminCanSeeAllStaffAttendanceForTargetDate(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 13, 10, 0, 0, 'Asia/Tokyo'));

        $admin = $this->createAdminUser();
        $staffA = $this->createStaffUser('一般 一郎', 'staff1@example.com');
        $staffB = $this->createStaffUser('一般 二郎', 'staff2@example.com');

        $target = Carbon::today();

        $this->createAttendance($staffA, $target, '09:00:00', '18:00:00', 60, 480);


        $res = $this->actingAs($admin)->get(route('admin.attendance.list'));
        $res->assertStatus(200);
        $res->assertSeeText('一般 一郎');
        $res->assertSeeText('09:00');
        $res->assertSeeText('18:00');
        $res->assertSeeText('1:00');
        $res->assertSeeText('8:00');
        $res->assertSeeText('一般 二郎');
        $res->assertSeeText('前日');
        $res->assertSeeText('翌日');
    }

    public function testAdminListShowsCurrentDateOnArrival(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 13, 10, 0, 0, 'Asia/Tokyo'));

        $admin = $this->createAdminUser();
        $res = $this->actingAs($admin)->get(route('admin.attendance.list'));
        $res->assertStatus(200);
        $res->assertSeeText('2025/12/13');
        $res->assertSee('2025年12月13日');
    }

    public function testAdminCanNavigateToPreviousDay(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 13, 10, 0, 0, 'Asia/Tokyo'));

        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser('一般 一郎', 'staff1@example.com');

        $today = Carbon::today();
        $yesterday = Carbon::today()->subDay();

        $this->createAttendance($staff, $yesterday, '10:00:00', '19:00:00', 45, 495);

        $res = $this->actingAs($admin)->get(route('admin.attendance.list', [
            'date' => $yesterday->format('Y-m-d'),
        ]));

        $res->assertStatus(200);
        $res->assertSeeText($yesterday->format('Y/m/d'));
        $res->assertSeeText('一般 一郎');
        $res->assertSeeText('10:00');
        $res->assertSeeText('19:00');
        $res->assertSeeText('0:45');
        $res->assertSeeText('8:15');
        $res->assertDontSee($today->format('Y/m/d'));
    }

    public function testAdminCanNavigateToNextDay(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 13, 10, 0, 0, 'Asia/Tokyo'));

        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser('一般 一郎', 'staff1@example.com');

        $tomorrow = Carbon::today()->addDay();

        $this->createAttendance($staff, $tomorrow, '08:30:00', '17:30:00', 60, 480);

        $res = $this->actingAs($admin)->get(route('admin.attendance.list', [
            'date' => $tomorrow->format('Y-m-d'),
        ]));

        $res->assertStatus(200);
        $res->assertSeeText($tomorrow->format('Y/m/d'));
        $res->assertSeeText('一般 一郎');
        $res->assertSeeText('08:30');
        $res->assertSeeText('17:30');
        $res->assertSeeText('1:00');
        $res->assertSeeText('8:00');
    }
}
