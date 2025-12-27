<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function testEmailIsRequiredForLogin(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('login'), [
            'email'    => '',
            'password' => 'dummy-password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);

        $this->assertGuest();
    }

    public function testPasswordIsRequiredForLogin(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('login'), [
            'email'    => $user->email,
            'password' => '',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);

        $this->assertGuest();
    }

    public function testLoginFailsWhenEmailDoesNotMatchRegisteredUser(): void
    {
        $user = User::factory()->create([
            'email'             => 'test@example.com',
            'password'          => 'correct-password',
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('login'), [
            'email'    => 'wrong@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);

        $this->assertGuest();
    }
}
