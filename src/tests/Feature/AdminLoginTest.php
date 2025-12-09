<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者ユーザーを作成する
     * ※ Userモデルの $fillable に is_admin が含まれていないため
     *   factory の配列指定だけだと反映されない可能性があるので
     *   forceFill で確実に true にする。
     */
    private function createAdminUser(array $overrides = []): User
    {
        $email = $overrides['email'] ?? 'admin@example.com';
        $rawPassword = $overrides['raw_password'] ?? 'password123';

        $user = User::factory()->create([
            'name' => $overrides['name'] ?? 'Admin User',
            'email' => $email,
            'password' => Hash::make($rawPassword),
        ]);

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        return $user;
    }

    /**
     * メールアドレス未入力 → バリデーションメッセージ表示
     */
    public function test_admin_login_requires_email(): void
    {
        $this->createAdminUser();

        $response = $this->from(route('admin.login'))
            ->post(route('admin.login.post'), [
                'email' => '',
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);

        $this->assertGuest();
    }

    /**
     * パスワード未入力 → バリデーションメッセージ表示
     */
    public function test_admin_login_requires_password(): void
    {
        $this->createAdminUser();

        $response = $this->from(route('admin.login'))
            ->post(route('admin.login.post'), [
                'email' => 'admin@example.com',
                'password' => '',
            ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);

        $this->assertGuest();
    }

    /**
     * 登録内容と一致しない（誤ったメールアドレス） → エラーメッセージ表示
     */
    public function test_admin_login_shows_error_when_email_is_not_registered(): void
    {
        $this->createAdminUser([
            'email' => 'admin@example.com',
            'raw_password' => 'password123',
        ]);

        $response = $this->from(route('admin.login'))
            ->post(route('admin.login.post'), [
                'email' => 'wrong@example.com',
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);

        $this->assertGuest();
    }

    /**
     * （任意）正常に管理者ログインできること
     * 仕様の3ケースには含まれていないが、
     * ルート/Controllerの整合性確認として入れておくと安全。
     */
    public function test_admin_can_login_successfully(): void
    {
        $admin = $this->createAdminUser([
            'email' => 'admin@example.com',
            'raw_password' => 'password123',
        ]);

        $response = $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.attendance.list'));
        $this->assertAuthenticatedAs($admin);
    }
}
