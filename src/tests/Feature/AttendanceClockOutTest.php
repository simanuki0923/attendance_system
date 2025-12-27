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

    public function testClockOutButtonWorksAndStatusChangesToAfterWork(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 12, 10, 18, 0, 0));

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

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

        $response = $this->get(route('attendance.list'));
        $response->assertStatus(200);
        $response->assertSee('退勤');
        $response = $this->post(route('attendance.clockOut'));
        $response->assertRedirect(route('attendance.list'));
        $response = $this->get(route('attendance.list'));
        $response->assertStatus(200);
        $response->assertSee('退勤済');

        $this->assertDatabaseHas('attendance_times', [
            'attendance_id' => $attendance->id,
            'end_time'      => '18:00:00',
        ]);
    }

    public function testClockOutTimeIsVisibleOnAttendanceList(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        Carbon::setTestNow(Carbon::create(2025, 12, 10, 9, 0, 0));
        $this->post(route('attendance.clockIn'))
            ->assertRedirect(route('attendance.list'));

        Carbon::setTestNow(Carbon::create(2025, 12, 10, 18, 0, 0));
        $this->post(route('attendance.clockOut'))
            ->assertRedirect(route('attendance.list'));

        $response = $this->get(route('attendance.userList'));
        $response->assertStatus(200);
        $response->assertSee('18:00');
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
