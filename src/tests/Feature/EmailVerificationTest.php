<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 会員登録後、認証メールが送信される
     * 1. 会員登録をする
     * 2. 認証メールを送信する
     * 期待挙動: 登録したメールアドレス宛に認証メールが送信されている
     */
    public function test_verification_email_is_sent_after_register(): void
    {
        Notification::fake();

        $response = $this->post(route('register'), [
            'name'                  => 'テスト太郎',
            'email'                 => 'taro@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // 会員登録完了 → メール認証案内画面へ（RegisterResponse）
        $response->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'taro@example.com')->firstOrFail();

        // 認証メール（通知）が送信されていること
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * メール認証誘導画面で「認証はこちらから」ボタンが表示される
     * (現状は mailto: のリンクになっているため、画面に存在することを検証)
     *
     * 1. メール認証導線画面を表示する
     * 2. 「認証はこちらから」ボタンを押下
     * 3. メール認証サイトを表示する
     * 期待挙動: メール認証サイトに遷移する
     */
    public function test_verify_email_notice_page_has_verify_button(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk();

        // verify-email.blade.php の「認証はこちらから」（mailtoリンク）が存在すること
        $response->assertSee('認証はこちらから', false);
        $response->assertSee('class="verify__button"', false);
        $response->assertSee('href="mailto:', false); // 現状コードに合わせる
    }

    /**
     * メール認証を完了すると、勤怠登録画面に遷移できる（= verified ルートへ入れる）
     *
     * 1. メール認証を完了する
     * 2. 勤怠登録画面を表示する
     * 期待挙動: 勤怠登録画面に遷移する
     */
    public function test_after_email_verification_user_can_access_attendance_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // 未認証の状態だと /attendance(=attendance.list) は verification.notice にリダイレクトされる
        $this->actingAs($user)
            ->get(route('attendance.list'))
            ->assertRedirect(route('verification.notice'));

        // 署名付きの認証URLを発行（verification.verify を叩く）
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id'   => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        // 認証URLへアクセス（ログイン状態が必要）
        $this->actingAs($user)->get($verificationUrl)->assertRedirect(); // どこへ返すかは実装依存のため redirect だけ確認

        // DB上で認証済みになっていること
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // 認証後は /attendance に入れる（勤怠登録画面へ）
        $this->actingAs($user)
            ->get(route('attendance.list'))
            ->assertOk();
    }

    /**
     * （任意：実運用的に強い）
     * 認証メール再送(verification.send)で VerifyEmail が再送される
     * verify-email.blade.php には再送フォームがあるため、その動作確認
     */
    public function test_resend_verification_email_sends_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->post(route('verification.send'));

        // verify-email.blade.php は session('status') === 'verification-link-sent' を見る
        $response->assertRedirect();
        $response->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
