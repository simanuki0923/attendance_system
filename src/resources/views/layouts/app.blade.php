@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;

    $headerType = trim($__env->yieldContent('header_type')) ?: 'default';

    $safeRoute = static function (string $routeName, string $fallbackRouteName): string {
        return Route::has($routeName) ? $routeName : $fallbackRouteName;
    };

    $user = Auth::user();

    $isAdmin = false;
    if ($user) {
        $isAdmin = (bool) ($user->is_admin ?? false)
            || in_array((string) ($user->email ?? ''), (array) config('admin.emails', []), true);
    }

    $homeRouteName = $isAdmin
        ? $safeRoute('admin.attendance.list', 'attendance.list')
        : $safeRoute('attendance.list', 'login');

    $logoutRouteName = $isAdmin
        ? $safeRoute('admin.logout', 'logout')
        : $safeRoute('logout', 'logout');

    $shouldHideMenu = Route::is(['login', 'register', 'verification.notice', 'admin.login']);

    $menu = [];
    if (! $shouldHideMenu && $user) {
        if ($isAdmin) {
            $menu = [
                ['label' => '勤怠一覧', 'route' => $safeRoute('admin.attendance.list', 'attendance.list')],
                ['label' => 'スタッフ一覧', 'route' => $safeRoute('admin.staff.list', 'attendance.list')],
                ['label' => '申請一覧', 'route' => $safeRoute('requests.list', 'attendance.list')],
            ];
        } elseif ($headerType === 'after_work') {
            $menu = [
                ['label' => '今月の出勤一覧', 'route' => $safeRoute('attendance.userList', 'attendance.list')],
                ['label' => '申請一覧', 'route' => $safeRoute('requests.list', 'attendance.list')],
            ];
        } else {
            $menu = [
                ['label' => '勤怠', 'route' => $safeRoute('attendance.list', 'attendance.list')],
                ['label' => '勤怠一覧', 'route' => $safeRoute('attendance.userList', 'attendance.list')],
                ['label' => '申請', 'route' => $safeRoute('requests.list', 'attendance.list')],
            ];
        }
    }
@endphp

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'COACHTECH')</title>

    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @yield('css')
</head>
<body>

<header class="header header--{{ $headerType }}">
    <div class="header__inner">

        <a href="{{ route($homeRouteName) }}" class="header__logo">
            <img src="{{ asset('img/logo.svg') }}" alt="COACHTECHロゴ">
        </a>

        @if (! $shouldHideMenu && $user)
            <nav class="header__nav" aria-label="メインナビゲーション">
                <ul class="header__nav-list">
                    @foreach ($menu as $item)
                        @php
                            $isActive = Route::is($item['route']);
                        @endphp
                        <li class="header__nav-item">
                            <a href="{{ route($item['route']) }}"
                               class="header__nav-link {{ $isActive ? 'is-active' : '' }}">
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach

                    <li class="header__nav-item">
                        <form method="POST" action="{{ route($logoutRouteName) }}">
                            @csrf
                            <button type="submit" class="header__nav-link header__nav-link--logout">
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
