<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;

class UserAttendanceDetailUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function seedStatuses(): ApplicationStatus
    {
        ApplicationStatus::query()->firstOrCreate(
            ['code' => ApplicationStatus::CODE_PENDING],
            ['label' => '承認待ち', 'sort_no' => 1]
        );

        ApplicationStatus::query()->firstOrCreate(
            ['code' => ApplicationStatus::CODE_APPROVED],
            ['label' => '承認済み', 'sort_no' => 2]
        );

        return ApplicationStatus::where('code', ApplicationStatus::CODE_PENDING)->firstOrFail();
    }

    private function createVerifiedUser(): User
    {
        return User::factory()->create([
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);
    }

    private function createAttendanceFor(User $user): Attendance
    {
        $attendance = Attendance::query()->create([
            'user_id'   => $user->id,
            'work_date' => now()->toDateString(),
            'note'      => '元の備考',
        ]);

        AttendanceTime::query()->create([
            'attendance_id' => $attendance->id,
            'start_time' => '09:00:00',
            'end_time'   => '18:00:00',
        ]);

        return $attendance->fresh();
    }

    public function test_start_time_after_end_time_shows_validation_error(): void
    {
        $this->seedStatuses();
        $user = $this->createVerifiedUser();
        $attendance = $this->createAttendanceFor($user);

        $response = $this->actingAs($user)->patch(
            route('attendance.detail.update', ['id' => $attendance->id]),
            [
                'start_time'   => '18:00',
                'end_time'     => '09:00',
                'break1_start' => null,
                'break1_end'   => null,
                'break2_start' => null,
                'break2_end'   => null,
                'note'         => '備考',
            ]
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'start_time' => '出勤時間が不適切な値です',
        ]);
    }

    public function test_break_start_after_end_time_shows_validation_error(): void
    {
        $this->seedStatuses();
        $user = $this->createVerifiedUser();
        $attendance = $this->createAttendanceFor($user);

        $response = $this->actingAs($user)->patch(
            route('attendance.detail.update', ['id' => $attendance->id]),
            [
                'start_time'   => '09:00',
                'end_time'     => '18:00',
                'break1_start' => '13:00',
                'break1_end'   => '12:00',
                'break2_start' => null,
                'break2_end'   => null,
                'note'         => '備考',
            ]
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'break1_start' => '休憩時間が不適切な値です',
        ]);
    }

    public function test_break_end_after_work_end_time_shows_validation_error(): void
    {
        $this->seedStatuses();
        $user = $this->createVerifiedUser();
        $attendance = $this->createAttendanceFor($user);

        $response = $this->actingAs($user)->patch(
            route('attendance.detail.update', ['id' => $attendance->id]),
            [
                'start_time'   => '09:00',
                'end_time'     => '18:00',
                'break1_start' => '17:50',
                'break1_end'   => '18:10',
                'break2_start' => null,
                'break2_end'   => null,
                'note'         => '備考',
            ]
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'break1_end' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    public function test_note_is_required_and_shows_validation_error(): void
    {
        $this->seedStatuses();
        $user = $this->createVerifiedUser();
        $attendance = $this->createAttendanceFor($user);

        $response = $this->actingAs($user)->patch(
            route('attendance.detail.update', ['id' => $attendance->id]),
            [
                'start_time'   => '09:10',
                'end_time'     => '18:10',
                'break1_start' => null,
                'break1_end'   => null,
                'break2_start' => null,
                'break2_end'   => null,
                'note'         => '',
            ]
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'note' => '備考を記入してください',
        ]);

        $this->assertDatabaseCount('attendance_applications', 0);
    }

    public function test_success_update_creates_application_only_and_does_not_change_attendance(): void
    {
        $pending = $this->seedStatuses();
        $user = $this->createVerifiedUser();
        $attendance = $this->createAttendanceFor($user);

        $response = $this->actingAs($user)->patch(
            route('attendance.detail.update', ['id' => $attendance->id]),
            [
                'start_time'   => '09:30',
                'end_time'     => '18:30',
                'break1_start' => '12:00',
                'break1_end'   => '13:00',
                'break2_start' => null,
                'break2_end'   => null,
                'note'         => '申請備考',
            ]
        );

        $response->assertStatus(302);
        $response->assertSessionHas('status');

        // 勤怠本体は変更されない（申請のみ作られる）前提
        $this->assertDatabaseHas('attendance_times', [
            'attendance_id' => $attendance->id,
            'start_time' => '09:00:00',
            'end_time'   => '18:00:00',
        ]);

        $this->assertDatabaseHas('attendance_applications', [
            'attendance_id'     => $attendance->id,
            'applicant_user_id' => $user->id,
            'status_id'         => $pending->id,
            'reason'            => '勤怠修正申請',
            'requested_work_start_time'   => '09:30:00',
            'requested_work_end_time'     => '18:30:00',
            'requested_break1_start_time' => '12:00:00',
            'requested_break1_end_time'   => '13:00:00',
            'requested_note'              => '申請備考',
        ]);
    }

    public function test_cannot_update_when_latest_application_is_pending(): void
    {
        $pending = $this->seedStatuses();
        $user = $this->createVerifiedUser();
        $attendance = $this->createAttendanceFor($user);

        AttendanceApplication::query()->create([
            'attendance_id'     => $attendance->id,
            'applicant_user_id' => $user->id,
            'status_id'         => $pending->id,
            'reason'            => '勤怠修正申請',
            'applied_at'        => now(),
        ]);

        $response = $this->actingAs($user)->patch(
            route('attendance.detail.update', ['id' => $attendance->id]),
            [
                'start_time'   => '09:30',
                'end_time'     => '18:30',
                'break1_start' => null,
                'break1_end'   => null,
                'break2_start' => null,
                'break2_end'   => null,
                'note'         => '備考',
            ]
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['application']);
    }
}
