<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\RegisterResponse;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 会員登録完了後のレスポンスを差し替え（メール認証誘導画面へ）
        $this->app->singleton(
            \Laravel\Fortify\Contracts\RegisterResponse::class,
            RegisterResponse::class
        );
    }

    public function boot(): void
    {
        // ====== 使用する Blade を指定（既存の auth/*.blade.php を利用） ======

        // 会員登録画面
        Fortify::registerView(function () {
            return view('auth.register');
        });

        // ログイン画面
        Fortify::loginView(function () {
            return view('auth.login');
        });

        // メール認証誘導画面
        Fortify::verifyEmailView(function () {
            return view('auth.verify-email');
        });

        // 会員登録処理
        Fortify::createUsersUsing(CreateNewUser::class);

        // ====== ログイン認証ロジック（FormRequestバリデーション + 認証チェック） ======
        Fortify::authenticateUsing(function (Request $request) {

            // ① LoginRequest のルール＆メッセージでバリデーション
            $formRequest = new LoginRequest();

            $validator = Validator::make(
                $request->all(),
                $formRequest->rules(),
                $formRequest->messages()
            );

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            // ② メールアドレスでユーザー検索
            $user = User::where('email', $request->string('email'))->first();

            // ★ メールが存在しない場合 → email欄の下に出す
            if (! $user) {
                throw ValidationException::withMessages([
                    'email' => 'ログイン情報が登録されていません',
                ]);
            }

            // ★ パスワードが違う場合 → password欄の下に出す
            if (! Hash::check($request->string('password'), $user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'ログイン情報が登録されていません',
                ]);
            }

            // ③ メール未認証なら再送 & メッセージだけ出してログインはさせない
            if (! $user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();

                session()->flash('must_verify', true);
                session()->flash('verification_link_sent', true);

                // 未認証系はグローバルで表示する想定のまま auth に付ける
                throw ValidationException::withMessages([
                    'auth' => 'メール認証が完了していません',
                ]);
            }

            // ④ 認証済ならログイン成功
            return $user;
        });

        // ====== レートリミッター定義 ======

        // login レートリミッター（1分あたり5回まで）
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by(
                    strtolower($request->string('email')) . '|' . $request->ip()
                ),
            ];
        });

        // 二要素認証用（使わなくても定義だけしておく）
        RateLimiter::for('two-factor', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->session()->get('login.id')),
            ];
        });
    }
}
