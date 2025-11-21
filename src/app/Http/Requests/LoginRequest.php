<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ログインフォームなので誰でもOK
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            // 1. 未入力の場合
            'email.required'    => 'メールアドレスを入力してください',
            'password.required' => 'パスワードを入力してください',

            // 形式エラーも日本語にしたいなら（任意）
            'email.email'       => 'メールアドレスを正しい形式で入力してください',
        ];
    }
}
