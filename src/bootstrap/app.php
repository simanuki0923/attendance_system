<?php

use Illuminate\Foundation\Application;
use App\Providers\FortifyServiceProvider;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AdminMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        /*
         |--------------------------------------------------------------------------
         | ルートミドルウェアの alias 登録
         |--------------------------------------------------------------------------
         | web.php 側で ['admin'] を使えるようにする
         |
         | Route::middleware(['auth', 'verified', 'admin'])->group(...)
         */

        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);

        // もし今後 alias を増やすならここへ追記する
        // $middleware->alias([
        //     'admin' => AdminMiddleware::class,
        //     'xxx'   => \App\Http\Middleware\XxxMiddleware::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
