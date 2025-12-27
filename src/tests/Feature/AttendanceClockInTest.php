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

    private function preparePendingStatus(): ApplicationStatus
    {
        return ApplicationStatus::create([
            'code'    => 'pending',
            'label'   => '承認待ち',
            'sort_no' => 1,
        ]);
    }

    private function createVerifiedUser(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function testClockInButtonWorksAndStatusChangesToWorking(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 10, 9, 0, 0));

        $user = $this->createVerifiedUser();
        $pending = $this->preparePendingStatus();

        $this->actingAs($user);

        $response = $this->get(route('attendance.list'));
        $response->assertStatus(200);
        $response->assertSeeText('出勤');
        $post = $this->post(route('attendance.clockIn'));
        $post->assertRedirect(route('attendance.list'));

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', Carbon::today())
            ->first();

        $this->assertNotNull($attendance, '当日の attendance レコードが作成されていません。');

        $this->assertDatabaseHas('attendance_times', [
            'attendance_id' => $attendance->id,
            'start_time'    => '09:00:00',
        ]);

        $this->assertDatabaseHas('attendance_totals', [
            'attendance_id' => $attendance->id,
        ]);

        $this->assertDatabaseHas('attendance_applications', [
            'attendance_id'     => $attendance->id,
            'applicant_user_id' => $user->id,
            'status_id'         => $pending->id,
        ]);

        $after = $this->get(route('attendance.list'));
        $after->assertStatus(200);
        $after->assertSeeText('退勤');
        $after->assertSeeText('休憩入');
    }

    public function testClockInButtonIsNotShownWhenAfterWork(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 10, 18, 0, 0));

        $user = $this->createVerifiedUser();

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

    public function testClockInTimeIsVisibleOnUserListPage(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 10, 9, 0, 0));

        $user = $this->createVerifiedUser();
        $this->preparePendingStatus();

        $this->actingAs($user);

        $this->post(route('attendance.clockIn'))
            ->assertRedirect(route('attendance.list'));

        $response = $this->get(route('attendance.userList'));
        $response->assertStatus(200);
        $response->assertSee('09:00');
    }
}
