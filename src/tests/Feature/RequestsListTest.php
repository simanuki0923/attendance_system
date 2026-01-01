<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;

class RequestsListTest extends TestCase
{
    use RefreshDatabase;

    private function seedStatuses(): array
    {
        $pending = ApplicationStatus::firstOrCreate(
            ['code' => 'pending'],
            ['label' => '承認待ち', 'sort_no' => 1]
        );

        $approved = ApplicationStatus::firstOrCreate(
            ['code' => 'approved'],
            ['label' => '承認済み', 'sort_no' => 2]
        );

        return [$pending, $approved];
    }

    public function test_user_can_view_own_pending_requests(): void
    {
        [$pending, $approved] = $this->seedStatuses();

        $user = User::factory()->create([
            'is_admin'          => false,
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id'   => $user->id,
            'work_date' => Carbon::today()->toDateString(),
            'note'      => 'テスト備考',
        ]);

        AttendanceTime::create([
            'attendance_id' => $attendance->id,
            'start_time'    => '09:00:00',
            'end_time'      => '18:00:00',
        ]);

        AttendanceApplication::create([
            'attendance_id'     => $attendance->id,
            'applicant_user_id' => $user->id,
            'status_id'         => $pending->id,
            'reason'            => '勤怠修正申請',
            'applied_at'        => now(),
        ]);

        $res = $this->actingAs($user)->get(route('requests.list', ['tab' => 'pending']));
        $res->assertOk();
        $res->assertSee('申請一覧');
        $res->assertSee('勤怠修正申請');
    }

    public function test_user_can_view_own_approved_requests(): void
    {
        [$pending, $approved] = $this->seedStatuses();

        $user = User::factory()->create([
            'is_admin'          => false,
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id'   => $user->id,
            'work_date' => Carbon::today()->toDateString(),
            'note'      => 'テスト備考',
        ]);

        AttendanceTime::create([
            'attendance_id' => $attendance->id,
            'start_time'    => '09:00:00',
            'end_time'      => '18:00:00',
        ]);

        AttendanceApplication::create([
            'attendance_id'     => $attendance->id,
            'applicant_user_id' => $user->id,
            'status_id'         => $approved->id,
            'reason'            => '勤怠修正申請',
            'applied_at'        => now(),
        ]);

        $res = $this->actingAs($user)->get(route('requests.list', ['tab' => 'approved']));
        $res->assertOk();
        $res->assertSee('申請一覧');
        $res->assertSee('勤怠修正申請');
    }
}
