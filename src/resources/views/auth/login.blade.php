<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COACHTECH</title>
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>
<body>
<header>
    <a href="/">
        <img src="{{ asset('img/logo.svg') }}" alt="COACHTECHロゴ">
    </a>
</header>

<main>
    <section class="login__content">
        <h1 class="login-form__heading">ログイン</h1>

        @if (session('intended_protected'))
            <div class="form__notice" role="status">
                認証が必要な操作のため、ログインしてください。
            </div>
        @endif

        @if (session('must_verify'))
            <div class="form__notice" role="status">
                メール認証が完了していません。<br>
                <a class="link--primary" href="{{ route('verification.notice') }}">認証はこちらから</a>
            </div>
        @endif

        @if (session('verification_link_sent'))
            <div class="form__notice" role="status">
                認証メールを再送しました。メールをご確認ください。
            </div>
        @endif

        <form class="form" action="{{ route('login') }}" method="POST" novalidate>
            @csrf

            <label class="form__group" for="email">
                <span class="form__label--item">メールアドレス</span>
                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required inputmode="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('email') ? 'error-email' : '' }}" />
                @error('email')
                    <span id="error-email" class="form__error" role="alert">
                        {{ $message }} {{-- メールアドレスを入力してください / メールアドレスを正しい形式で入力してください --}}
                    </span>
                @enderror
            </label>

            <label class="form__group" for="password">
                <span class="form__label--item">パスワード</span>
                <input id="password" type="password" name="password" autocomplete="current-password" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" aria-describedby="{{ $errors->has('password') ? 'error-password' : '' }}" />
                @error('password')
                    <span id="error-password" class="form__error" role="alert">
                        {{ $message }} {{-- パスワードを入力してください --}}
                    </span>
                @enderror
            </label>
            <button class="form__button-submit" type="submit">ログインする</button>
        </form>

        <p class="register__link">
            <a class="register__button-submit" href="{{ route('register') }}">会員登録はこちら</a>
        </p>
    </section>
</main>
</body>
</html>
