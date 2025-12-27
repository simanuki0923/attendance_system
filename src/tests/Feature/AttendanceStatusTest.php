<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceBreak;
use Carbon\Carbon;

class AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2025-12-10 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createVerifiedUser(array $override = []): User
    {
        return User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'is_admin'          => false,
        ], $override));
    }

    private function createTodayAttendance(User $user): Attendance
    {
        return Attendance::create([
            'user_id'   => $user->id,
            'work_date' => today()->toDateString(),
            'note'      => null,
        ]);
    }

    public function testStatusLabelIsBeforeWorkWhenUserHasNotClockedIn()
    {
        $user = $this->createVerifiedUser();

        $response = $this->actingAs($user)->get(route('attendance.list'));

        $response->assertOk();
        $response->assertSee('勤務外');
        $response->assertSee('attendance__badge--before_work', false);
    }

    public function testStatusLabelIsWorkingWhenUserHasStartTimeAndNoOpenBreak()
    {
        $user = $this->createVerifiedUser();

        $attendance = $this->createTodayAttendance($user);

        AttendanceTime::create([
            'attendance_id' => $attendance->id,
            'start_time'    => '09:00:00',
            'end_time'      => null,
        ]);

        $response = $this->actingAs($user)->get(route('attendance.list'));

        $response->assertOk();
        $response->assertSee('出勤中');
        $response->assertSee('attendance__badge--working', false);
    }

    public function testStatusLabelIsOnBreakWhenUserHasOpenBreak()
    {
        $user = $this->createVerifiedUser();

        $attendance = $this->createTodayAttendance($user);

        AttendanceTime::create([
            'attendance_id' => $attendance->id,
            'start_time'    => '09:00:00',
            'end_time'      => null,
        ]);

        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_no'      => 1,
            'start_time'    => '12:00:00',
            'end_time'      => null,
            'minutes'       => 0,
        ]);

        $response = $this->actingAs($user)->get(route('attendance.list'));

        $response->assertOk();
        $response->assertSee('休憩中');
        $response->assertSee('attendance__badge--on_break', false);
    }

    public function testStatusLabelIsAfterWorkWhenUserHasEndTime()
    {
        $user = $this->createVerifiedUser();

        $attendance = $this->createTodayAttendance($user);

        AttendanceTime::create([
            'attendance_id' => $attendance->id,
            'start_time'    => '09:00:00',
            'end_time'      => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('attendance.list'));

        $response->assertOk();
        $response->assertSee('退勤済');
        $response->assertSee('attendance__badge--after_work', false);
    }
}
