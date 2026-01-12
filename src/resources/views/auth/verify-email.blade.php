<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COACHTECH</title>
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/email.css') }}">
</head>
<body>
<header>
    <a href="/">
        <img src="{{ asset('img/logo.svg') }}" alt="COACHTECHロゴ">
    </a>
</header>

<main>
    <section class="verify__content">
        @if (session('status') === 'verification-link-sent')
            <p class="verify__status">
                認証用リンクを再送しました。メールをご確認ください。
            </p>
        @endif

        <p class="verify__lead">
            登録していただいたメールアドレスに認証メールを送付しました。<br>
            メール認証を完了してください。
        </p>

        <a class="verify__button" href="mailto:">認証はこちらから</a>

        <form class="verify__resend" method="post" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="verify__resend-link">認証メールを再送する</button>
        </form>
    </section>
</main>
</body>
</html>
