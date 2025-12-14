<?php

namespace App\Actions\Fortify;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return redirect()->route('verification.notice');
    }
}
