<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\RegisterResponse;
use App\Http\Requests\LoginRequest as AppLoginRequest;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 会員登録完了後のレスポンスを差し替え
        $this->app->singleton(
            \Laravel\Fortify\Contracts\RegisterResponse::class,
            RegisterResponse::class
        );

        /**
         * ★重要
         * Fortify標準のLoginRequestを
         * App側のLoginRequestへ差し替える
         */
        $this->app->bind(FortifyLoginRequest::class, AppLoginRequest::class);
    }

    public function boot(): void
    {
        // ====== 使用する Blade を指定 ======

        Fortify::registerView(function () {
            return view('auth.register');
        });

        Fortify::loginView(function () {
            return view('auth.login');
        });

        Fortify::verifyEmailView(function () {
            return view('auth.verify-email');
        });

        Fortify::createUsersUsing(CreateNewUser::class);

        // ====== ログイン認証ロジック ======
        Fortify::authenticateUsing(function (Request $request) {

            $email    = (string) $request->input('email');
            $password = (string) $request->input('password');

            // ① メールアドレスでユーザー検索
            $user = User::where('email', $email)->first();

            // 入力情報が誤っている場合（メールが存在しない）
            if (! $user) {
                throw ValidationException::withMessages([
                    'email' => 'ログイン情報が登録されていません',
                ]);
            }

            // 入力情報が誤っている場合（パスワード不一致）
            if (! Hash::check($password, $user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'ログイン情報が登録されていません',
                ]);
            }

            // ② メール未認証なら再送 & ログイン不可
            // Userが MustVerifyEmail を実装している前提でOK
            // （あなたの User モデルは実装済み）:contentReference[oaicite:5]{index=5}
            if (! $user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();

                session()->flash('must_verify', true);
                session()->flash('verification_link_sent', true);

                throw ValidationException::withMessages([
                    'auth' => 'メール認証が完了していません',
                ]);
            }

            return $user;
        });

        // ====== レートリミッター ======
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by(
                    strtolower($email) . '|' . $request->ip()
                ),
            ];
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->session()->get('login.id')),
            ];
        });
    }
}
