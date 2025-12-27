<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttendanceDateTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testAttendanceScreenDisplaysCurrentDatetimeInUiFormat(): void
    {
        $fixedNow = Carbon::create(2025, 12, 10, 9, 15, 0, config('app.timezone'));
        Carbon::setTestNow($fixedNow);

        $user = User::factory()->create([
            'is_admin' => false,
            'email_verified_at' => $fixedNow,
        ]);

        $expectedDate = Carbon::today()->locale('ja')->isoFormat('YYYY年M月D日(ddd)');
        $expectedTime = Carbon::now()->format('H:i');

        $response = $this->actingAs($user)
            ->get(route('attendance.list'));

        $response->assertStatus(200);
        $response->assertSee($expectedDate, false);
        $response->assertSee($expectedTime, false);
    }

    public function testUnverifiedUserIsRedirectedFromAttendanceScreen(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->get(route('attendance.list'));

        $response->assertStatus(302);
        $response->assertRedirect(route('verification.notice'));
    }
}
