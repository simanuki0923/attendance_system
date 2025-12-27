<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceTotal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2025, 11, 10, 9, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createVerifiedUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email_verified_at' => now(),
        ], $attributes));
    }

    private function createAttendanceForDate(
        User $user,
        Carbon $date,
        string $startTime,
        string $endTime,
        int $breakMinutes,
        int $totalWorkMinutes
    ): Attendance {
        $attendance = Attendance::create([
            'user_id'   => $user->id,
            'work_date' => $date->toDateString(),
            'note'      => 'テスト備考',
        ]);

        AttendanceTime::create([
            'attendance_id' => $attendance->id,
            'start_time'    => $startTime,
            'end_time'      => $endTime,
        ]);

        AttendanceTotal::create([
            'attendance_id'      => $attendance->id,
            'break_minutes'      => $breakMinutes,
            'total_work_minutes' => $totalWorkMinutes,
        ]);

        return $attendance;
    }

    public function testAttendanceListShowsAllAttendancesForLoggedInUser(): void
    {
        $user = $this->createVerifiedUser();

        $targetMonth = Carbon::create(2025, 11, 1);

        $this->createAttendanceForDate(
            $user,
            $targetMonth->copy()->day(5),
            '09:00:00',
            '18:00:00',
            60,
            480
        );

        $this->createAttendanceForDate(
            $user,
            $targetMonth->copy()->day(6),
            '10:00:00',
            '19:00:00',
            90,
            510
        );

        $otherUser = $this->createVerifiedUser(['email' => 'other@example.com']);
        $this->createAttendanceForDate(
            $otherUser,
            $targetMonth->copy()->day(5),
            '00:00:00',
            '01:00:00',
            0,
            60
        );

        $response = $this
            ->actingAs($user)
            ->get(route('attendance.userList', ['month' => '2025-11']));

        $response->assertStatus(200);
        $response->assertSee('11/05');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('1:00');
        $response->assertSee('8:00');
        $response->assertSee('11/06');
        $response->assertSee('10:00');
        $response->assertSee('19:00');
        $response->assertSee('1:30');
        $response->assertSee('8:30');
    }

    public function testAttendanceListDisplaysCurrentMonthWhenMonthQueryIsAbsent(): void
    {
        $user = $this->createVerifiedUser();

        $response = $this
            ->actingAs($user)
            ->get(route('attendance.userList'));

        $response->assertStatus(200);

        $response->assertSee('2025/11');
    }

    public function testPrevMonthButtonShowsPreviousMonthAttendances(): void
    {
        $user = $this->createVerifiedUser();

        $prevMonth = Carbon::create(2025, 10, 1);

        $this->createAttendanceForDate(
            $user,
            $prevMonth->copy()->day(1),
            '09:00:00',
            '18:00:00',
            60,
            480
        );

        $response = $this
            ->actingAs($user)
            ->get(route('attendance.userList', ['month' => '2025-10']));

        $response->assertStatus(200);
        $response->assertSee('2025/10');
        $response->assertSee('10/01');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    public function testNextMonthButtonShowsNextMonthAttendances(): void
    {
        $user = $this->createVerifiedUser();

        $october = Carbon::create(2025, 10, 1);
        $november = Carbon::create(2025, 11, 1);

        $this->createAttendanceForDate(
            $user,
            $november->copy()->day(2),
            '09:00:00',
            '18:00:00',
            60,
            480
        );

        $initialResponse = $this
            ->actingAs($user)
            ->get(route('attendance.userList', ['month' => '2025-10']));

        $initialResponse->assertStatus(200);

        $initialResponse->assertSee('?month=2025-11');

        $nextResponse = $this
            ->actingAs($user)
            ->get(route('attendance.userList', ['month' => '2025-11']));

        $nextResponse->assertStatus(200);
        $nextResponse->assertSee('2025/11');
        $nextResponse->assertSee('11/02');
        $nextResponse->assertSee('09:00');
        $nextResponse->assertSee('18:00');
    }

    public function testDetailLinkNavigatesToAttendanceDetailPage(): void
    {
        $user = $this->createVerifiedUser();

        $month = Carbon::create(2025, 11, 1);

        $attendance = $this->createAttendanceForDate(
            $user,
            $month->copy()->day(5),
            '09:00:00',
            '18:00:00',
            60,
            480
        );

        $listResponse = $this
            ->actingAs($user)
            ->get(route('attendance.userList', ['month' => '2025-11']));

        $listResponse->assertStatus(200);

        $detailUrl = route('attendance.detail', ['id' => $attendance->id]);

        $listResponse->assertSee($detailUrl);

        $detailResponse = $this
            ->actingAs($user)
            ->get($detailUrl);

        $detailResponse->assertStatus(200);
    }
}
