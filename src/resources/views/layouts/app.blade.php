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

    // 各画面から @section('header_type', 'after_work') などで渡せる
    $headerType = trim($__env->yieldContent('header_type'));
@endphp

<header class="header">
    <div class="header__inner">
        {{-- ロゴのみクリックでトップへ --}}
        <a href="{{ route('home') }}" class="header__logo">
            <img src="{{ asset('img/logo.svg') }}" alt="COACHTECHロゴ">
        </a>

        {{-- ログイン系画面はロゴだけ（ナビ非表示） --}}
        @if (Route::is(['login', 'register', 'verification.notice']))
            {{-- 何も表示しない --}}
        @elseif ($user)
            {{-- ログイン済み画面はナビを表示 --}}
            @php
                // ===== メニュー定義 =====
                // デフォルト：一般ユーザー（出勤前 / 通常）
                $menu = [
                    ['label' => '勤怠',     'route' => 'attendance.list'],
                    ['label' => '勤怠一覧', 'route' => 'attendance.list'],
                    ['label' => '申請',     'route' => 'requests.list'],
                ];

                // 管理者（is_admin フラグ想定）
                if ($user->is_admin ?? false) {
                    $menu = [
                        ['label' => '勤怠',     'route' => 'admin.attendance.list'],
                        ['label' => '勤怠一覧', 'route' => 'admin.attendance.list'],
                        ['label' => '申請',     'route' => 'admin.requests.list'],
                    ];
                }
                // 一般ユーザー 退勤後画面
                elseif ($headerType === 'after_work') {
                    $menu = [
                        ['label' => '今月の出勤一覧', 'route' => 'attendance.month'],
                        ['label' => '申請一覧',       'route' => 'requests.list'],
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

                    {{-- ログアウト --}}
                    <li class="header__nav-item">
                        <form method="POST" action="{{ route('logout') }}">
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
