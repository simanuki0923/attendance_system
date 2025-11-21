<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        Validator::make(
            $input,
            [
                'name'                  => ['required', 'string', 'max:100'],
                'email'                 => ['required', 'string', 'email', 'max:255'],
                'password'              => ['required', 'string', 'min:8', 'confirmed'],
            ],
            [
                'name.required'         => 'お名前を入力してください',
                'email.required'        => 'メールアドレスを入力してください',
                'password.required'     => 'パスワードを入力してください',
                'password.min'          => 'パスワードは8文字以上で入力してください',
                'password.confirmed'    => 'パスワードと一致しません',
            ]
        )->validate();

        return User::create([
            'name'     => $input['name'],
            'email'    => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
