<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function testNameIsRequired(): void
    {
        $response = $this->post(route('register'),
            $this->validPayload(['name' => ''])
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
        ]);
    }

    public function testEmailIsRequired(): void
    {
        $response = $this->post(route('register'),
            $this->validPayload(['email' => ''])
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    public function testPasswordIsRequired(): void
    {
        $response = $this->post(route('register'),
            $this->validPayload([
                'password' => '',
                'password_confirmation' => '',
            ])
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    public function testPasswordMustBeAtLeast8Characters(): void
    {
        $response = $this->post(route('register'),
            $this->validPayload([
                'password' => 'short7', // 7文字未満想定
                'password_confirmation' => 'short7',
            ])
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください',
        ]);
    }

    public function testPasswordConfirmationMustMatch(): void
    {
        $response = $this->post(route('register'),
            $this->validPayload([
                'password' => 'password123',
                'password_confirmation' => 'password999',
            ])
        );

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'password' => 'パスワードと一致しません',
        ]);
    }

    public function testUserCanRegisterWithValidInput(): void
    {
        Notification::fake();

        $payload = $this->validPayload([
            'name' => 'テスト太郎',
            'email' => 'valid@example.com',
        ]);

        $response = $this->post(route('register'), $payload);

        $response->assertStatus(302);
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'テスト太郎',
            'email' => 'valid@example.com',
            'is_admin' => false,
        ]);

        $this->assertTrue(
            User::where('email', 'valid@example.com')->exists()
        );
    }
}
