<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
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
            // 未入力
            'email.required'    => 'メールアドレスを入力してください',
            'password.required' => 'パスワードを入力してください',

            // 形式（必要なら）
            'email.email'       => 'メールアドレスを正しい形式で入力してください',
        ];
    }
}
