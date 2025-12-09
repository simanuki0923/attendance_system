<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * メールアドレスが未入力の場合、
     * 「メールアドレスを入力してください」が表示される
     *
     * 手順（確認コード.txt 準拠）:
     * 1. ユーザーを登録する
     * 2. メールアドレス以外のユーザー情報を入力する
     * 3. ログインの処理を行う
     */
    public function test_email_is_required_for_login(): void
    {
        // 1. ユーザーを登録する（メール認証済みにしておく）
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // 2-3. メール以外は入力してログイン
        $response = $this->post(route('login'), [
            'email'    => '',
            'password' => 'dummy-password',
        ]);

        // 期待挙動
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);

        $this->assertGuest();
    }

    /**
     * パスワードが未入力の場合、
     * 「パスワードを入力してください」が表示される
     *
     * 手順（確認コード.txt 準拠）:
     * 1. ユーザーを登録する
     * 2. パスワード以外のユーザー情報を入力する
     * 3. ログインの処理を行う
     */
    public function test_password_is_required_for_login(): void
    {
        // 1. ユーザーを登録する（メール認証済みにしておく）
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // 2-3. パスワード以外は入力してログイン
        $response = $this->post(route('login'), [
            'email'    => $user->email,
            'password' => '',
        ]);

        // 期待挙動
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);

        $this->assertGuest();
    }

    /**
     * 登録内容と一致しない場合、
     * 「ログイン情報が登録されていません」が表示される
     *
     * 手順（確認コード.txt 準拠）:
     * 1. ユーザーを登録する
     * 2. 誤ったメールアドレスのユーザー情報を入力する
     * 3. ログインの処理を行う
     */
    public function test_login_fails_when_email_does_not_match_registered_user(): void
    {
        // 1. ユーザーを登録する
        // User モデルは password が hashed cast のため平文指定でOK
        $user = User::factory()->create([
            'email'             => 'test@example.com',
            'password'          => 'correct-password',
            'email_verified_at' => now(),
        ]);

        // 2-3. 誤ったメールアドレスでログイン
        $response = $this->post(route('login'), [
            'email'    => 'wrong@example.com',
            'password' => 'correct-password',
        ]);

        // Fortify の authenticateUsing 実装では
        // メールが存在しない場合は email キーで同メッセージが返る
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);

        $this->assertGuest();
    }
}
