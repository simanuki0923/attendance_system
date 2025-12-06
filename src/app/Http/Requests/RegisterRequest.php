<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 会員登録は誰でもOK
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100', // users.name(100) に合わせる
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // 1. 未入力の場合
            'name.required'      => 'お名前を入力してください',
            'email.required'     => 'メールアドレスを入力してください',
            'password.required'  => 'パスワードを入力してください',

            // 2. パスワードの入力規則違反の場合
            'password.min'       => 'パスワードは8文字以上で入力してください',

            // 3. 確認用パスワードの入力規則違反の場合
            'password.confirmed' => 'パスワードと一致しません',
        ];
    }
}
