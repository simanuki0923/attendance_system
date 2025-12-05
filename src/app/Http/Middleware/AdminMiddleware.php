<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('admin.login');
        }

        // ★管理者判定：is_admin true OR ホワイトリスト一致
        $isAdmin =
            (bool)($user->is_admin ?? false)
            || in_array($user->email, config('admin.emails', []), true);

        if (!$isAdmin) {
            abort(403, '管理者のみアクセスできます。');
        }

        return $next($request);
    }
}
