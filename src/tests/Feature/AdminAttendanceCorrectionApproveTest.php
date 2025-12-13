<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use PHPUnit\Framework\Attributes\Test;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceBreak;
use App\Models\AttendanceApplication;
use App\Models\ApplicationStatus;

class AdminAttendanceCorrectionApproveTest extends TestCase
{
    use RefreshDatabase;

    private ApplicationStatus $pending;
    private ApplicationStatus $approved;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pending = ApplicationStatus::create([
            'code'    => 'pending',
            'label'   => '承認待ち',
            'sort_no' => 1,
        ]);

        $this->approved = ApplicationStatus::create([
            'code'    => 'approved',
            'label'   => '承認済み',
            'sort_no' => 2,
        ]);
    }

    private function makeAdminUser(): User
    {
        $admin = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // is_admin は fillable に無いので forceFill
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function makeAttendanceWithTimeAndBreaks(User $user, string $workDateYmd): Attendance
    {
        $attendance = Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $workDateYmd,
            'note'      => 'テスト備考',
        ]);

        AttendanceTime::create([
            'attendance_id' => $attendance->id,
            'start_time'    => '09:00:00',
            'end_time'      => '18:00:00',
        ]);

        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_no'      => 1,
            'start_time'    => '12:00:00',
            'end_time'      => '13:00:00',
            'minutes'       => 60,
        ]);

        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_no'      => 2,
            'start_time'    => '15:00:00',
            'end_time'      => '15:30:00',
            'minutes'       => 30,
        ]);

        return $attendance;
    }

    private function makeApplication(
        Attendance $attendance,
        User $applicant,
        ApplicationStatus $status,
        ?Carbon $appliedAt = null
    ): AttendanceApplication {
        return AttendanceApplication::create([
            'attendance_id'     => $attendance->id,
            'applicant_user_id' => $applicant->id,
            'status_id'         => $status->id,
            'reason'            => '勤怠修正申請',
            'applied_at'        => ($appliedAt ?? now())->toDateTimeString(),
        ]);
    }

    #[Test]
    public function pending_tab_shows_all_pending_requests(): void
    {
        $admin = $this->makeAdminUser();

        $u1 = User::factory()->create(['name' => '一般A', 'email_verified_at' => now()]);
        $u2 = User::factory()->create(['name' => '一般B', 'email_verified_at' => now()]);
        $u3 = User::factory()->create(['name' => '一般C', 'email_verified_at' => now()]);

        $a1 = $this->makeAttendanceWithTimeAndBreaks($u1, '2025-12-01');
        $a2 = $this->makeAttendanceWithTimeAndBreaks($u2, '2025-12-02');
        $a3 = $this->makeAttendanceWithTimeAndBreaks($u3, '2025-12-03');

        $this->makeApplication($a1, $u1, $this->pending,  Carbon::parse('2025-12-05 10:00:00'));
        $this->makeApplication($a2, $u2, $this->pending,  Carbon::parse('2025-12-05 11:00:00'));
        $this->makeApplication($a3, $u3, $this->approved, Carbon::parse('2025-12-05 12:00:00'));

        $res = $this->actingAs($admin)->get(route('requests.list', ['tab' => 'pending']));
        $res->assertOk();

        $res->assertSee('承認待ち');
        $res->assertSee('承認済み');

        $res->assertSee('一般A');
        $res->assertSee('一般B');
        $res->assertDontSee('一般C');
    }

    #[Test]
    public function approved_tab_shows_all_approved_requests(): void
    {
        $admin = $this->makeAdminUser();

        $u1 = User::factory()->create(['name' => '一般A', 'email_verified_at' => now()]);
        $u2 = User::factory()->create(['name' => '一般B', 'email_verified_at' => now()]);

        $a1 = $this->makeAttendanceWithTimeAndBreaks($u1, '2025-12-01');
        $a2 = $this->makeAttendanceWithTimeAndBreaks($u2, '2025-12-02');

        $this->makeApplication($a1, $u1, $this->approved, Carbon::parse('2025-12-06 10:00:00'));
        $this->makeApplication($a2, $u2, $this->pending,  Carbon::parse('2025-12-06 11:00:00'));

        $res = $this->actingAs($admin)->get(route('requests.list', ['tab' => 'approved']));
        $res->assertOk();

        $res->assertSee('一般A');
        $res->assertDontSee('一般B');
    }

    #[Test]
    public function approve_detail_page_shows_correct_application_contents(): void
    {
        $admin = $this->makeAdminUser();

        $user = User::factory()->create(['name' => '一般A', 'email_verified_at' => now()]);
        $attendance = $this->makeAttendanceWithTimeAndBreaks($user, '2025-12-01');

        $app = $this->makeApplication($attendance, $user, $this->pending, Carbon::parse('2025-12-05 10:00:00'));

        $res = $this->actingAs($admin)->get(
            route('stamp_correction_request.approve.show', ['attendance_correct_request_id' => $app->id])
        );

        $res->assertOk();

        $res->assertSee('一般A');
        $res->assertSee('2025年');
        $res->assertSee('12月1日');

        $res->assertSee('09:00');
        $res->assertSee('18:00');
        $res->assertSee('12:00');
        $res->assertSee('13:00');
        $res->assertSee('15:00');
        $res->assertSee('15:30');

        $res->assertSee('テスト備考');

        $res->assertSee('承認');
        $res->assertDontSee('承認済み');
    }

    #[Test]
    public function approving_request_marks_application_approved_and_updates_attendance_total(): void
    {
        $admin = $this->makeAdminUser();

        $user = User::factory()->create(['name' => '一般A', 'email_verified_at' => now()]);
        $attendance = $this->makeAttendanceWithTimeAndBreaks($user, '2025-12-01');

        $app = $this->makeApplication($attendance, $user, $this->pending);

        $detailUrl = route('stamp_correction_request.approve.show', ['attendance_correct_request_id' => $app->id]);

        $res = $this->actingAs($admin)
            ->from($detailUrl)
            ->post(route('stamp_correction_request.approve', ['attendance_correct_request_id' => $app->id]));

        $res->assertRedirect($detailUrl);

        $this->assertDatabaseHas('attendance_applications', [
            'id'        => $app->id,
            'status_id' => $this->approved->id,
        ]);

        // 09:00-18:00 = 540分、休憩60+30=90分 → 実働450分
        $this->assertDatabaseHas('attendance_totals', [
            'attendance_id'      => $attendance->id,
            'break_minutes'      => 90,
            'total_work_minutes' => 450,
        ]);

        $show = $this->actingAs($admin)->get($detailUrl);
        $show->assertOk();
        $show->assertSee('承認済み');
    }
}
