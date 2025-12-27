<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceTotal;

class AdminUserInfoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff1;
    private User $staff2;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('admin.emails', []);

        Carbon::setTestNow(Carbon::create(2025, 12, 15, 9, 0, 0, 'Asia/Tokyo'));

        $this->admin = User::factory()->create([
            'name'  => '管理者',
            'email' => 'admin@example.com',
        ]);
        $this->admin->forceFill(['is_admin' => true])->save();

        $this->staff1 = User::factory()->create([
            'name'  => '山田太郎',
            'email' => 'taro@example.com',
        ]);

        $this->staff2 = User::factory()->create([
            'name'  => '鈴木花子',
            'email' => 'hanako@example.com',
        ]);
    }

    public function test_admin_can_see_all_staff_names_and_emails_on_staff_list(): void
    {
        $res = $this->actingAs($this->admin)
            ->get(route('admin.staff.list'));
        $res->assertOk();
        $res->assertSee('スタッフ一覧');
        $res->assertSee($this->staff1->name);
        $res->assertSee($this->staff1->email);
        $res->assertSee($this->staff2->name);
        $res->assertSee($this->staff2->email);
        $res->assertSee(route('admin.attendance.staff', ['id' => $this->staff1->id]));
        $res->assertSee(route('admin.attendance.staff', ['id' => $this->staff2->id]));
    }

    public function test_admin_can_view_selected_staff_monthly_attendance_list_and_data_is_correct(): void
    {
        $attendance = Attendance::create([
            'user_id'   => $this->staff1->id,
            'work_date' => '2025-12-10',
            'note'      => null,
        ]);

        AttendanceTime::create([
            'attendance_id' => $attendance->id,
            'start_time'    => '09:00:00',
            'end_time'      => '18:00:00',
        ]);

        AttendanceTotal::create([
            'attendance_id'      => $attendance->id,
            'break_minutes'      => 60,
            'total_work_minutes' => 420,
        ]);

        $res = $this->actingAs($this->admin)
            ->get(route('admin.attendance.staff', [
                'id'    => $this->staff1->id,
                'month' => '2025-12',
            ]));

        $res->assertOk();
        $res->assertSee($this->staff1->name . 'さんの勤怠');
        $res->assertSee('2025/12');
        $res->assertSee('12/10(');
        $res->assertSee('09:00');
        $res->assertSee('18:00');
        $res->assertSee('1:00');
        $res->assertSee('7:00');
        $res->assertSee(route('admin.attendance.detail', ['id' => $attendance->id]));
    }

    public function test_prev_month_link_shows_previous_month(): void
    {
        $dec = $this->actingAs($this->admin)
            ->get(route('admin.attendance.staff', [
                'id'    => $this->staff1->id,
                'month' => '2025-12',
            ]));

        $dec->assertOk();
        $dec->assertSee('2025/12');

        $prevUrl = route('admin.attendance.staff', [
            'id'    => $this->staff1->id,
            'month' => '2025-11',
        ]);
        $dec->assertSee($prevUrl);

        $nov = $this->actingAs($this->admin)->get($prevUrl);
        $nov->assertOk();
        $nov->assertSee('2025/11');
    }

    public function test_next_month_link_shows_next_month(): void
    {
        $dec = $this->actingAs($this->admin)
            ->get(route('admin.attendance.staff', [
                'id'    => $this->staff1->id,
                'month' => '2025-12',
            ]));

        $dec->assertOk();
        $dec->assertSee('2025/12');

        $nextUrl = route('admin.attendance.staff', [
            'id'    => $this->staff1->id,
            'month' => '2026-01',
        ]);
        $dec->assertSee($nextUrl);

        $jan = $this->actingAs($this->admin)->get($nextUrl);
        $jan->assertOk();
        $jan->assertSee('2026/01');
    }

    public function test_detail_link_navigates_to_attendance_detail_page(): void
    {
        $attendance = Attendance::create([
            'user_id'   => $this->staff1->id,
            'work_date' => '2025-12-10',
            'note'      => null,
        ]);

        AttendanceTime::create([
            'attendance_id' => $attendance->id,
            'start_time'    => '09:00:00',
            'end_time'      => '18:00:00',
        ]);

        AttendanceTotal::create([
            'attendance_id'      => $attendance->id,
            'break_minutes'      => 60,
            'total_work_minutes' => 420,
        ]);

        $list = $this->actingAs($this->admin)
            ->get(route('admin.attendance.staff', [
                'id'    => $this->staff1->id,
                'month' => '2025-12',
            ]));

        $list->assertOk();

        $detailUrl = route('admin.attendance.detail', ['id' => $attendance->id]);
        $list->assertSee($detailUrl);

        $detail = $this->actingAs($this->admin)->get($detailUrl);
        $detail->assertOk();
    }
}
