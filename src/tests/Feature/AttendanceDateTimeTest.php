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
        // テスト時刻固定を解除
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * ◆日時取得機能
     * ・テスト内容
     *   現在の日時情報がUIと同じ形式で出力されている
     * ・テスト手順
     *   1. 勤怠打刻画面を開く
     *   2. 画面に表示されている日時情報を確認する
     * ・期待挙動
     *   画面上に表示されている日時が現在の日時と一致する
     *
     * 確認コード.txt の仕様に対応
     */
    public function test_attendance_screen_displays_current_datetime_in_ui_format(): void
    {
        // 1) 現在時刻を固定（テストを安定させる）
        $fixedNow = Carbon::create(2025, 12, 10, 9, 15, 0, config('app.timezone'));
        Carbon::setTestNow($fixedNow);

        // 2) メール認証済の一般ユーザーを用意
        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'is_admin' => false,
            'email_verified_at' => $fixedNow,
        ]);

        // 3) 期待値（Controller/Blade と同じロジックで生成）
        //    Controller:
        //      $displayDate = Carbon::today()->locale('ja')->isoFormat('YYYY年M月D日(ddd)');
        //      $displayTime = now()->format('H:i');
        $expectedDate = Carbon::today()->locale('ja')->isoFormat('YYYY年M月D日(ddd)');
        $expectedTime = Carbon::now()->format('H:i');

        // 4) 勤怠打刻画面へアクセス
        $response = $this->actingAs($user)
            ->get(route('attendance.list'));

        // 5) 表示確認
        $response->assertStatus(200);
        $response->assertSee($expectedDate, false);
        $response->assertSee($expectedTime, false);
    }

    /**
     * 未メール認証ユーザーは勤怠打刻画面にアクセスできない
     * （ルートの verified ミドルウェア仕様の安全確認）
     */
    public function test_unverified_user_is_redirected_from_attendance_screen(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->get(route('attendance.list'));

        $response->assertStatus(302);
        // verification.notice へ飛ぶ想定
        $response->assertRedirect(route('verification.notice'));
    }
}
