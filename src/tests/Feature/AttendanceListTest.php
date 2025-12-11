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

        // ★ テスト内で「現在の月」を固定（2025年11月）
        Carbon::setTestNow(Carbon::create(2025, 11, 10, 9, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // リセット
        parent::tearDown();
    }

    /**
     * 便利メソッド：メール認証済みの一般ユーザーを作成
     */
    private function createVerifiedUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email_verified_at' => now(),
            // is_admin はデフォルト false（一般ユーザー）
        ], $attributes));
    }

    /**
     * 便利メソッド：指定日の勤怠 + 時間 + 合計をまとめて作成
     */
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

    /**
     * 【テスト1】
     * 自分が行った勤怠情報がすべて表示されている
     */
    public function test_attendance_list_shows_all_attendances_for_logged_in_user(): void
    {
        $user = $this->createVerifiedUser();

        // 対象月：2025年11月
        $targetMonth = Carbon::create(2025, 11, 1);

        // ログインユーザーの勤怠を2日分作成
        $this->createAttendanceForDate(
            $user,
            $targetMonth->copy()->day(5),
            '09:00:00',
            '18:00:00',
            60,   // 休憩 60分 → 1:00
            480   // 合計 480分 → 8:00
        );

        $this->createAttendanceForDate(
            $user,
            $targetMonth->copy()->day(6),
            '10:00:00',
            '19:00:00',
            90,   // 休憩 90分 → 1:30
            510   // 合計 510分 → 8:30
        );

        // 別ユーザーの勤怠（一覧には出ないことを想定）
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

        // 11/05 の情報が表示されているか
        $response->assertSee('11/05');  // 日付（曜日付きだが部分一致でOK）
        $response->assertSee('09:00');  // 出勤時間
        $response->assertSee('18:00');  // 退勤時間
        $response->assertSee('1:00');   // 休憩 60分
        $response->assertSee('8:00');   // 合計 480分

        // 11/06 の情報が表示されているか
        $response->assertSee('11/06');
        $response->assertSee('10:00');
        $response->assertSee('19:00');
        $response->assertSee('1:30');   // 休憩 90分
        $response->assertSee('8:30');   // 合計 510分
    }

    /**
     * 【テスト2】
     * 勤怠一覧画面に遷移した際に現在の月が表示される
     */
    public function test_attendance_list_displays_current_month_when_month_query_is_absent(): void
    {
        $user = $this->createVerifiedUser();

        $response = $this
            ->actingAs($user)
            ->get(route('attendance.userList'));

        $response->assertStatus(200);

        // setUp で現在日付を 2025-11-10 に固定しているので、表示は「2025/11」のはず
        $response->assertSee('2025/11');
    }

    /**
     * 【テスト3】
     * 「前月」を押下した時に表示月の前月の情報が表示される
     *
     * 実装的には、クエリパラメータ month=YYYY-MM に応じて
     * 対象月が切り替わるので、それを直接叩いて検証する。
     */
    public function test_prev_month_button_shows_previous_month_attendances(): void
    {
        $user = $this->createVerifiedUser();

        // 前月：2025年10月
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

        // 月表示が「2025/10」になっているか
        $response->assertSee('2025/10');

        // 10/01 の勤怠が表示されているか
        $response->assertSee('10/01');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    /**
     * 【テスト4】
     * 「翌月」を押下した時に表示月の翌月の情報が表示される
     *
     * 実装では「次月が現在の月より未来すぎる場合はリンク無効」なので、
     * 2025/11 を「現在の月」とみなし、そこから見た「前月 2025/10」画面に
     * 翌月=2025/11 へのリンクが出るケースを検証する。
     */
    public function test_next_month_button_shows_next_month_attendances(): void
    {
        $user = $this->createVerifiedUser();

        $october = Carbon::create(2025, 10, 1);
        $november = Carbon::create(2025, 11, 1);

        // 翌月（= 現在の月 2025/11）の勤怠データ
        $this->createAttendanceForDate(
            $user,
            $november->copy()->day(2),
            '09:00:00',
            '18:00:00',
            60,
            480
        );

        // まず 2025/10 の画面を表示（ここに「翌月」リンクがある想定）
        $initialResponse = $this
            ->actingAs($user)
            ->get(route('attendance.userList', ['month' => '2025-10']));

        $initialResponse->assertStatus(200);

        // 「翌月」ボタンが 2025-11 を指すリンクを持っているか（ザックリチェック）
        $initialResponse->assertSee('?month=2025-11');

        // 実際に「翌月」に相当する 2025/11 を表示させる
        $nextResponse = $this
            ->actingAs($user)
            ->get(route('attendance.userList', ['month' => '2025-11']));

        $nextResponse->assertStatus(200);
        $nextResponse->assertSee('2025/11');
        $nextResponse->assertSee('11/02');
        $nextResponse->assertSee('09:00');
        $nextResponse->assertSee('18:00');
    }

    /**
     * 【テスト5】
     * 「詳細」を押下すると、その日の勤怠詳細画面に遷移する
     *
     * 一覧画面で生成される detail_url が /attendance/detail/{id} になっていることと、
     * そのURLにアクセスすると 200 が返ることを確認する。
     */
    public function test_detail_link_navigates_to_attendance_detail_page(): void
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

        // 勤怠一覧画面を表示
        $listResponse = $this
            ->actingAs($user)
            ->get(route('attendance.userList', ['month' => '2025-11']));

        $listResponse->assertStatus(200);

        $detailUrl = route('attendance.detail', ['id' => $attendance->id]);

        // 一覧画面のHTML内に「詳細」リンク（= detailUrl）が含まれているか
        $listResponse->assertSee($detailUrl);

        // 実際に詳細ページへアクセスすると 200 が返るか
        $detailResponse = $this
            ->actingAs($user)
            ->get($detailUrl);

        $detailResponse->assertStatus(200);
    }
}
