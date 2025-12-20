<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::user();

        if ($user === null) {
            return redirect()->route('admin.login');
        }

        $adminEmails = config('admin.emails', []);

        $isAdmin = (bool) ($user->is_admin ?? false)
            || in_array((string) $user->email, $adminEmails, true);

        if (! $isAdmin) {
            return redirect()
                ->route('admin.login')
                ->withErrors(['auth' => 'ログイン情報が登録されていません']);
        }

        return $next($request);
    }
}
