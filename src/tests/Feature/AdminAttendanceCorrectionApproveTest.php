<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;

class AdminAttendanceCorrectionApproveTest extends TestCase
{
    use RefreshDatabase;

    private function seedStatuses(): array
    {
        $pending = ApplicationStatus::query()->firstOrCreate(
            ['code' => ApplicationStatus::CODE_PENDING],
            ['label' => '承認待ち', 'sort_no' => 1]
        );

        $approved = ApplicationStatus::query()->firstOrCreate(
            ['code' => ApplicationStatus::CODE_APPROVED],
            ['label' => '承認済み', 'sort_no' => 2]
        );

        return [$pending, $approved];
    }

    public function test_admin_can_approve_and_apply_requested_values(): void
    {
        [$pending, $approved] = $this->seedStatuses();

        $admin = User::factory()->create([
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $staff = User::factory()->create([
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::query()->create([
            'user_id'   => $staff->id,
            'work_date' => now()->toDateString(),
            'note'      => '元の備考',
        ]);

        AttendanceTime::query()->create([
            'attendance_id' => $attendance->id,
            'start_time' => '09:00:00',
            'end_time'   => '18:00:00',
        ]);

        $app = AttendanceApplication::query()->create([
            'attendance_id'     => $attendance->id,
            'applicant_user_id' => $staff->id,
            'status_id'         => $pending->id,
            'reason'            => '勤怠修正申請',
            'applied_at'        => now(),

            'requested_work_start_time'   => '10:00:00',
            'requested_work_end_time'     => '19:00:00',
            'requested_break1_start_time' => '12:00:00',
            'requested_break1_end_time'   => '13:00:00',
            'requested_note'              => '承認後の備考',
        ]);

        $response = $this->actingAs($admin)->post(route('stamp_correction_request.approve', [
            'attendance_correct_request_id' => $app->id,
        ]));

        $response->assertStatus(302);
        $response->assertSessionHas('status', '承認しました');

        $this->assertDatabaseHas('attendance_applications', [
            'id' => $app->id,
            'status_id' => $approved->id,
        ]);

        $this->assertDatabaseHas('attendance_times', [
            'attendance_id' => $attendance->id,
            'start_time' => '10:00:00',
            'end_time'   => '19:00:00',
        ]);

        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_no' => 1,
            'start_time' => '12:00:00',
            'end_time'   => '13:00:00',
            'minutes'    => 60,
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'note' => '承認後の備考',
        ]);

        $this->assertDatabaseHas('attendance_totals', [
            'attendance_id' => $attendance->id,
            'break_minutes' => 60,
            'total_work_minutes' => 480,
        ]);
    }
}
