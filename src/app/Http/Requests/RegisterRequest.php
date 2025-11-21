<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * 認可（会員登録は誰でもOKなので true）
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルール
     *
     * ・Fortifyデフォルト相当（name / email / password）
     * ・パスワードは 8文字以上 & 確認用と一致（confirmed）
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:' . User::class,
            ],
            'password' => [
                'required',
                'string',
                'min:8',     // 8文字以上
                'confirmed', // password_confirmation と一致
            ],
        ];
    }

    /**
     * 日本語のエラーメッセージ
     *
     * 指示いただいた内容に対応
     */
    public function messages(): array
    {
        return [
            // 1. 未入力の場合
            'name.required'     => 'お名前を入力してください',
            'email.required'    => 'メールアドレスを入力してください',
            'password.required' => 'パスワードを入力してください',

            // 2. パスワードの入力規則違反の場合
            'password.min'      => 'パスワードは8文字以上で入力してください',

            // 3. 確認用パスワードの入力規則違反の場合
            'password.confirmed' => 'パスワードと一致しません',
        ];
    }
}
