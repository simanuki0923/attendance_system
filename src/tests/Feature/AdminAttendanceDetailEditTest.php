<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceBreak;

class AdminAttendanceDetailEditTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        return $admin;
    }

    private function createAttendanceWithRelations(array $overrides = []): Attendance
    {
        $employee = User::factory()->create([
            'name' => $overrides['employee_name'] ?? '一般ユーザー太郎',
        ]);

        $attendance = Attendance::create([
            'user_id'   => $employee->id,
            'work_date' => $overrides['work_date'] ?? '2025-12-01',
            'note'      => $overrides['note'] ?? 'テスト備考',
        ]);

        AttendanceTime::create([
            'attendance_id' => $attendance->id,
            'start_time'    => $overrides['start_time_db'] ?? '09:00:00',
            'end_time'      => $overrides['end_time_db'] ?? '18:00:00',
        ]);

        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_no'      => 1,
            'start_time'    => $overrides['break1_start_db'] ?? '12:00:00',
            'end_time'      => $overrides['break1_end_db'] ?? '13:00:00',
            'minutes'       => $overrides['break1_minutes'] ?? 60,
        ]);

        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_no'      => 2,
            'start_time'    => $overrides['break2_start_db'] ?? '15:00:00',
            'end_time'      => $overrides['break2_end_db'] ?? '15:15:00',
            'minutes'       => $overrides['break2_minutes'] ?? 15,
        ]);

        return Attendance::with(['user', 'time', 'breaks'])->findOrFail($attendance->id);
    }

    private function assertInputHasTimeHtml(string $html, string $name, string $expectedHm): void
    {
        $expectedHms = $expectedHm . ':00';

        $patternFindInput = '/<input\b[^>]*\bname\s*=\s*([\'"])' . preg_quote($name, '/') . '\1[^>]*>/si';

        if (!preg_match_all($patternFindInput, $html, $matches)) {
            $this->fail("input[name={$name}] がHTML内に見つかりません。");
        }

        $ok = false;
        foreach ($matches[0] as $inputTag) {
            $patternValueHm  = '/\bvalue\s*=\s*([\'"])' . preg_quote($expectedHm, '/')  . '\1/i';
            $patternValueHms = '/\bvalue\s*=\s*([\'"])' . preg_quote($expectedHms, '/') . '\1/i';

            if (preg_match($patternValueHm, $inputTag) || preg_match($patternValueHms, $inputTag)) {
                $ok = true;
                break;
            }
        }

        $this->assertTrue(
            $ok,
            "input[name={$name}] に {$expectedHm}（または {$expectedHms}）がセットされていません。"
        );
    }

    public function test_admin_attendance_detail_page_shows_selected_data(): void
    {
        $admin = $this->createAdminUser();

        $attendance = $this->createAttendanceWithRelations([
            'employee_name'   => '山田 太郎',
            'work_date'       => '2025-12-01',
            'note'            => '備考テスト',
            'start_time_db'   => '09:00:00',
            'end_time_db'     => '18:00:00',
            'break1_start_db' => '12:00:00',
            'break1_end_db'   => '13:00:00',
            'break2_start_db' => '15:00:00',
            'break2_end_db'   => '15:15:00',
        ]);

        $url = route('admin.attendance.detail', ['id' => $attendance->id]);

        $response = $this->actingAs($admin)->get($url);

        $response->assertOk();
        $response->assertSee('勤怠詳細');
        $response->assertSee('山田 太郎');
        $response->assertSee('2025年');
        $response->assertSee('12月1日');
        $response->assertSee('備考テスト');

        $html = $response->getContent();

        $this->assertInputHasTimeHtml($html, 'start_time', '09:00');
        $this->assertInputHasTimeHtml($html, 'end_time', '18:00');

        $this->assertInputHasTimeHtml($html, 'break1_start', '12:00');
        $this->assertInputHasTimeHtml($html, 'break1_end', '13:00');

        $this->assertInputHasTimeHtml($html, 'break2_start', '15:00');
        $this->assertInputHasTimeHtml($html, 'break2_end', '15:15');
    }

    public function test_start_time_after_end_time_shows_validation_message(): void
    {
        $admin = $this->createAdminUser();
        $attendance = $this->createAttendanceWithRelations();

        $showUrl = route('admin.attendance.detail', ['id' => $attendance->id]);
        $updateUrl = route('admin.attendance.detail.update', ['id' => $attendance->id]);

        $payload = [
            'start_time'    => '19:00',
            'end_time'      => '18:00',
            'break1_start'  => '12:00',
            'break1_end'    => '13:00',
            'break2_start'  => '15:00',
            'break2_end'    => '15:15',
            'note'          => '更新テスト',
        ];

        $response = $this->actingAs($admin)->from($showUrl)->patch($updateUrl, $payload);

        $response->assertRedirect($showUrl);
        $response->assertSessionHasErrors();

        $this->followRedirects($response)
            ->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }

    public function test_break_start_after_end_time_shows_validation_message(): void
    {
        $admin = $this->createAdminUser();
        $attendance = $this->createAttendanceWithRelations();

        $showUrl = route('admin.attendance.detail', ['id' => $attendance->id]);
        $updateUrl = route('admin.attendance.detail.update', ['id' => $attendance->id]);

        $payload = [
            'start_time'    => '09:00',
            'end_time'      => '18:00',
            'break1_start'  => '19:00',
            'break1_end'    => '19:10',
            'break2_start'  => '15:00',
            'break2_end'    => '15:15',
            'note'          => '更新テスト',
        ];

        $response = $this->actingAs($admin)->from($showUrl)->patch($updateUrl, $payload);

        $response->assertRedirect($showUrl);
        $response->assertSessionHasErrors();

        $this->followRedirects($response)
            ->assertSee('休憩時間が不適切な値です');
    }

    public function test_break_end_after_end_time_shows_validation_message(): void
    {
        $admin = $this->createAdminUser();
        $attendance = $this->createAttendanceWithRelations();

        $showUrl = route('admin.attendance.detail', ['id' => $attendance->id]);
        $updateUrl = route('admin.attendance.detail.update', ['id' => $attendance->id]);

        $payload = [
            'start_time'    => '09:00',
            'end_time'      => '18:00',
            'break1_start'  => '12:00',
            'break1_end'    => '19:00',
            'break2_start'  => '15:00',
            'break2_end'    => '15:15',
            'note'          => '更新テスト',
        ];

        $response = $this->actingAs($admin)->from($showUrl)->patch($updateUrl, $payload);

        $response->assertRedirect($showUrl);
        $response->assertSessionHasErrors();

        $this->followRedirects($response)
            ->assertSee('休憩時間もしくは退勤時間が不適切な値です');
    }

    public function test_note_empty_shows_validation_message(): void
    {
        $admin = $this->createAdminUser();
        $attendance = $this->createAttendanceWithRelations();

        $showUrl = route('admin.attendance.detail', ['id' => $attendance->id]);
        $updateUrl = route('admin.attendance.detail.update', ['id' => $attendance->id]);

        $payload = [
            'start_time'    => '09:00',
            'end_time'      => '18:00',
            'break1_start'  => '12:00',
            'break1_end'    => '13:00',
            'break2_start'  => '15:00',
            'break2_end'    => '15:15',
            'note'          => '',
        ];

        $response = $this->actingAs($admin)->from($showUrl)->patch($updateUrl, $payload);

        $response->assertRedirect($showUrl);
        $response->assertSessionHasErrors();

        $this->followRedirects($response)
            ->assertSee('備考を記入してください');
    }
}
