<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'COACHTECH')</title>

    {{-- 共通CSS --}}
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @yield('css')
</head>
<body>
@php
    /** @var \App\Models\User|null $user */
    $user = auth()->user();

    $headerType = trim($__env->yieldContent('header_type'));

    $safeRoute = function (string $primary, string $fallback) {
        return \Illuminate\Support\Facades\Route::has($primary) ? $primary : $fallback;
    };

    // ★FIX: 管理者判定（is_admin / whitelist 両対応で統一）
    $isAdmin = false;
    if ($user) {
        $isAdmin =
            (bool)($user->is_admin ?? false)
            || in_array($user->email, config('admin.emails', []), true);
    }

    // ★FIX: バナー（ロゴ）の遷移先も管理者/一般で分岐
    $homeRouteName = $isAdmin
        ? $safeRoute('admin.attendance.list', 'attendance.list')
        : $safeRoute('home', 'attendance.list'); // 一般は従来通り /home 経由でOK

    $logoutRoute = $isAdmin
        ? $safeRoute('admin.logout', 'logout')
        : 'logout';
@endphp

<header class="header">
    <div class="header__inner">

        {{-- ★FIX: 管理者は admin 勤怠一覧へ --}}
        <a href="{{ route($homeRouteName) }}" class="header__logo">
            <img src="{{ asset('img/logo.svg') }}" alt="COACHTECHロゴ">
        </a>

        @if (Route::is(['login', 'register', 'verification.notice', 'admin.login']))
            {{-- ナビ非表示 --}}
        @elseif ($user)
            @php
                // 1) 管理者メニュー
                if ($isAdmin) {
                    $menu = [
                        [
                            'label' => '勤怠一覧',
                            'route' => $safeRoute('admin.attendance.list', 'attendance.list'),
                        ],
                        [
                            'label' => 'スタッフ一覧',
                            'route' => $safeRoute('admin.staff.list', 'attendance.list'),
                        ],
                        [
                            'label' => '申請一覧',
                            'route' => $safeRoute('requests.list', 'attendance.list'),
                        ],
                    ];
                }

                // 2) 一般ユーザー 退勤後
                elseif ($headerType === 'after_work') {
                    $menu = [
                        [
                            'label' => '今月の出勤一覧',
                            'route' => $safeRoute('attendance.userList', 'attendance.list'),
                        ],
                        [
                            'label' => '申請一覧',
                            'route' => $safeRoute('requests.list', 'attendance.list'),
                        ],
                    ];
                }

                // 3) 一般ユーザー
                else {
                    $menu = [
                        [
                            'label' => '勤怠',
                            'route' => $safeRoute('attendance.list', 'attendance.list'),
                        ],
                        [
                            'label' => '勤怠一覧',
                            'route' => $safeRoute('attendance.userList', 'attendance.list'),
                        ],
                        [
                            'label' => '申請',
                            'route' => $safeRoute('requests.list', 'attendance.list'),
                        ],
                    ];
                }
            @endphp

            <nav class="header__nav">
                <ul class="header__nav-list">
                    @foreach ($menu as $item)
                        <li class="header__nav-item">
                            <a href="{{ route($item['route']) }}"
                               class="header__nav-link {{ Route::is($item['route']) ? 'is-active' : '' }}">
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach

                    <li class="header__nav-item">
                        <form method="POST" action="{{ route($logoutRoute) }}">
                            @csrf
                            <button type="submit"
                                    class="header__nav-link header__nav-link--logout">
                                ログアウト
                            </button>
                        </form>
                    </li>
                </ul>
            </nav>
        @endif
    </div>
</header>

<main class="main">
    @yield('content')
</main>

</body>
</html>
