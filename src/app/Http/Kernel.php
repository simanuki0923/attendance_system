<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

// --- Global Middleware ---
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Middleware\TrustProxies;

// --- Web Middleware ---
use App\Http\Middleware\EncryptCookies;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;

// --- Route / Alias Middleware ---
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;

// 標準の認証・署名・レート制限など
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\AuthenticateSession;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Routing\Middleware\Authorize;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Auth\Middleware\RequirePassword;

// ★あなたの管理者ミドルウェア
use App\Http\Middleware\AdminMiddleware;

class Kernel extends HttpKernel
{
    /**
     * アプリ全体にかかるグローバルミドルウェア
     */
    protected $middleware = [
        TrustProxies::class,
        HandleCors::class,
        PreventRequestsDuringMaintenance::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
    ];

    /**
     * ミドルウェアグループ（web / api）
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ],

        'api' => [
            ThrottleRequests::class . ':api',
            SubstituteBindings::class,
        ],
    ];

    /**
     * ルートで使うミドルウェアのエイリアス
     * routes/web.php の middleware(['auth','verified','admin']) の参照先
     */
    protected $middlewareAliases = [
        // 認証系
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'auth.session' => AuthenticateSession::class,
        'guest' => RedirectIfAuthenticated::class,

        // 認可・確認系
        'can' => Authorize::class,
        'password.confirm' => RequirePassword::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,

        // キャッシュヘッダなど
        'cache.headers' => SetCacheHeaders::class,

        // ★管理者判定（is_admin を見る版の AdminMiddleware）
        'admin' => AdminMiddleware::class,
    ];
}
