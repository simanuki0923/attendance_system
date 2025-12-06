<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // まず未ログイン対策
        if (! $user) {
            return redirect()->route('admin.login');
        }

        // is_admin OR ホワイトリスト
        $isAdmin =
            (bool) ($user->is_admin ?? false)
            || in_array($user->email, config('admin.emails', []), true);

        if (! $isAdmin) {
            // 仕様に寄せて文言は統一
            return redirect()->route('admin.login')
                ->withErrors(['auth' => 'ログイン情報が登録されていません']);
        }

        return $next($request);
    }
}
