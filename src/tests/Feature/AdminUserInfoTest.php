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

        // ホワイトリスト依存を消す（is_admin=true で通す）
        config()->set('admin.emails', []);

        // 日付固定（StaffController が Carbon::today() を使うため）
        Carbon::setTestNow(Carbon::create(2025, 12, 15, 9, 0, 0, 'Asia/Tokyo'));

        // 管理者
        $this->admin = User::factory()->create([
            'name'  => '管理者',
            'email' => 'admin@example.com',
        ]);
        $this->admin->is_admin = true;
        $this->admin->save();

        // 一般ユーザー（スタッフ）
        $this->staff1 = User::factory()->create([
            'name'  => '山田太郎',
            'email' => 'taro@example.com',
        ]);

        $this->staff2 = User::factory()->create([
            'name'  => '鈴木花子',
            'email' => 'hanako@example.com',
        ]);
    }

    /**
     * 管理者が全一般ユーザーの「氏名」「メールアドレス」を確認できる
     * 1) 管理者でログイン 2) スタッフ一覧を開く 期待：全員分表示
     */
    public function test_admin_can_see_all_staff_names_and_emails_on_staff_list(): void
    {
        $res = $this->actingAs($this->admin)
            ->get(route('admin.staff.list'));

        $res->assertOk();

        // スタッフ一覧タイトル
        $res->assertSee('スタッフ一覧');

        // 一般ユーザー（氏名/メール）が表示される
        $res->assertSee($this->staff1->name);
        $res->assertSee($this->staff1->email);

        $res->assertSee($this->staff2->name);
        $res->assertSee($this->staff2->email);

        // 「詳細」リンク（スタッフ別勤怠ページ）が生成される
        $res->assertSee(route('admin.staff.attendance', ['user' => $this->staff1->id]));
        $res->assertSee(route('admin.staff.attendance', ['user' => $this->staff2->id]));
    }

    /**
     * ユーザーの勤怠情報が正しく表示される
     * 1) 管理者ログイン 2) 選択したユーザーの勤怠一覧を開く 期待：勤怠が正確に表示
     */
    public function test_admin_can_view_selected_staff_monthly_attendance_list_and_data_is_correct(): void
    {
        // 2025-12-10 の勤怠を作成
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
            'break_minutes'      => 60,   // 1:00
            'total_work_minutes' => 420,  // 7:00（表示用）
        ]);

        $res = $this->actingAs($this->admin)
            ->get(route('admin.staff.attendance', [
                'user'  => $this->staff1->id,
                'month' => '2025-12',
            ]));

        $res->assertOk();

        // 見出し（「◯◯さんの勤怠」）
        $res->assertSee($this->staff1->name . 'さんの勤怠');

        // 表示月
        $res->assertSee('2025/12');

        // 日付行（曜日は環境差があるので "12/10(" までを見る）
        $res->assertSee('12/10(');

        // 出勤/退勤
        $res->assertSee('09:00');
        $res->assertSee('18:00');

        // 休憩/合計（StaffController の fmtMinutes 表示）
        $res->assertSee('1:00');
        $res->assertSee('7:00');

        // 詳細リンク（attendance.detail が存在する場合に出る）
        $res->assertSee(route('attendance.detail', ['id' => $attendance->id]));
    }

    /**
     * 「前月」を押下した時に表示月の前月の情報が表示される
     */
    public function test_prev_month_link_shows_previous_month(): void
    {
        $dec = $this->actingAs($this->admin)
            ->get(route('admin.staff.attendance', [
                'user'  => $this->staff1->id,
                'month' => '2025-12',
            ]));

        $dec->assertOk();
        $dec->assertSee('2025/12');

        // 前月URLが表示される（2025-11）
        $prevUrl = route('admin.staff.attendance', [
            'user'  => $this->staff1->id,
            'month' => '2025-11',
        ]);
        $dec->assertSee($prevUrl);

        // 前月ページへ遷移した想定
        $nov = $this->actingAs($this->admin)->get($prevUrl);
        $nov->assertOk();
        $nov->assertSee('2025/11');
    }

    /**
     * 「翌月」を押下した時に表示月の翌月の情報が表示される
     */
    public function test_next_month_link_shows_next_month(): void
    {
        $dec = $this->actingAs($this->admin)
            ->get(route('admin.staff.attendance', [
                'user'  => $this->staff1->id,
                'month' => '2025-12',
            ]));

        $dec->assertOk();
        $dec->assertSee('2025/12');

        // 翌月URLが表示される（2026-01）
        $nextUrl = route('admin.staff.attendance', [
            'user'  => $this->staff1->id,
            'month' => '2026-01',
        ]);
        $dec->assertSee($nextUrl);

        // 翌月ページへ遷移した想定
        $jan = $this->actingAs($this->admin)->get($nextUrl);
        $jan->assertOk();
        $jan->assertSee('2026/01');
    }

    /**
     * 「詳細」を押下すると、その日の勤怠詳細画面に遷移する
     */
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

        // スタッフ別勤怠一覧
        $list = $this->actingAs($this->admin)
            ->get(route('admin.staff.attendance', [
                'user'  => $this->staff1->id,
                'month' => '2025-12',
            ]));

        $list->assertOk();

        $detailUrl = route('attendance.detail', ['id' => $attendance->id]);
        $list->assertSee($detailUrl);

        // 詳細画面へ（管理者なので attendance.detail は EditController 側に入る想定）
        $detail = $this->actingAs($this->admin)->get($detailUrl);
        $detail->assertOk();
    }
}
