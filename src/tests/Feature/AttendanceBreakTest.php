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

    public function testBreakInButtonWorksAndStatusChangesToOnBreak(): void
    {
        Carbon::setTestNow('2025-12-10 10:00:00');

        [$user, $attendance] = $this->createWorkingUserWithAttendance();

        $response = $this->actingAs($user)->get(route('attendance.list'));
        $response
            ->assertStatus(200)
            ->assertSee('休憩入');

        $response = $this->actingAs($user)->post(route('attendance.breakIn'));
        $response->assertRedirect(route('attendance.list'));

        $this->assertEquals('on_break', session('attendance_status'));

        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_no'      => 1,
        ]);

        $response = $this->actingAs($user)->get(route('attendance.list'));
        $response
            ->assertStatus(200)
            ->assertSee('休憩中')
            ->assertSee('休憩戻')
            ->assertDontSee('休憩入');
    }

    public function testBreakCanBeTakenMultipleTimesInADay(): void
    {
        Carbon::setTestNow('2025-12-10 10:00:00');

        [$user, $attendance] = $this->createWorkingUserWithAttendance();

        $this->actingAs($user)
            ->post(route('attendance.breakIn'))
            ->assertRedirect(route('attendance.list'));

        Carbon::setTestNow('2025-12-10 10:30:00');

        $this->actingAs($user)
            ->post(route('attendance.breakOut'))
            ->assertRedirect(route('attendance.list'));

        $response = $this->actingAs($user)->get(route('attendance.list'));
        $response
            ->assertStatus(200)
            ->assertSee('出勤中')
            ->assertSee('休憩入');
    }

    public function testBreakOutButtonWorksAndStatusChangesBackToWorking(): void
    {
        Carbon::setTestNow('2025-12-10 10:00:00');

        [$user, $attendance] = $this->createWorkingUserWithAttendance();

        $this->actingAs($user)
            ->post(route('attendance.breakIn'))
            ->assertRedirect(route('attendance.list'));

        $this->assertEquals('on_break', session('attendance_status'));

        Carbon::setTestNow('2025-12-10 10:30:00');

        $this->actingAs($user)
            ->post(route('attendance.breakOut'))
            ->assertRedirect(route('attendance.list'));

        $this->assertEquals('working', session('attendance_status'));

        $break = AttendanceBreak::where('attendance_id', $attendance->id)
            ->orderBy('break_no')
            ->first();

        $this->assertNotNull($break);
        $this->assertNotNull($break->end_time);
        $this->assertGreaterThanOrEqual(0, $break->minutes);
    }

    public function testBreakOutCanBeDoneMultipleTimesInADay(): void
    {
        Carbon::setTestNow('2025-12-10 10:00:00');

        [$user, $attendance] = $this->createWorkingUserWithAttendance();

        $this->actingAs($user)
            ->post(route('attendance.breakIn'))
            ->assertRedirect(route('attendance.list'));

        Carbon::setTestNow('2025-12-10 10:30:00');

        $this->actingAs($user)
            ->post(route('attendance.breakOut'))
            ->assertRedirect(route('attendance.list'));

        Carbon::setTestNow('2025-12-10 11:00:00');

        $this->actingAs($user)
            ->post(route('attendance.breakIn'))
            ->assertRedirect(route('attendance.list'));

        $response = $this->actingAs($user)->get(route('attendance.list'));
        $response
            ->assertStatus(200)
            ->assertSee('休憩中')
            ->assertSee('休憩戻');
    }

    public function testBreakTimesAreRecordedCorrectlyInDatabase(): void
    {
        Carbon::setTestNow('2025-12-10 12:00:00');

        [$user, $attendance] = $this->createWorkingUserWithAttendance();

        $this->actingAs($user)
            ->post(route('attendance.breakIn'))
            ->assertRedirect(route('attendance.list'));

        Carbon::setTestNow('2025-12-10 12:30:00');

        $this->actingAs($user)
            ->post(route('attendance.breakOut'))
            ->assertRedirect(route('attendance.list'));

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

        return [$user, $attendance];
    }
}
