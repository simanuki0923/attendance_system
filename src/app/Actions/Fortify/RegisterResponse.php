<?php

namespace App\Actions\Fortify;

use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        // 会員登録完了 → メール認証案内画面へ
        return redirect()->route('verification.notice');
    }
}
