<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(array $overrides = []): User
    {
        $email = $overrides['email'] ?? 'admin@example.com';
        $rawPassword = $overrides['raw_password'] ?? 'password123';

        $user = User::factory()->create([
            'name' => $overrides['name'] ?? 'Admin User',
            'email' => $email,
            'password' => Hash::make($rawPassword),
        ]);

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        return $user;
    }

    public function testAdminLoginRequiresEmail(): void
    {
        $this->createAdminUser();

        $response = $this->from(route('admin.login'))
            ->post(route('admin.login.post'), [
                'email' => '',
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);

        $this->assertGuest();
    }

    public function testAdminLoginRequiresPassword(): void
    {
        $this->createAdminUser();

        $response = $this->from(route('admin.login'))
            ->post(route('admin.login.post'), [
                'email' => 'admin@example.com',
                'password' => '',
            ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);

        $this->assertGuest();
    }

    public function testAdminLoginShowsErrorWhenEmailIsNotRegistered(): void
    {
        $this->createAdminUser([
            'email' => 'admin@example.com',
            'raw_password' => 'password123',
        ]);

        $response = $this->from(route('admin.login'))
            ->post(route('admin.login.post'), [
                'email' => 'wrong@example.com',
                'password' => 'password123',
            ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);

        $this->assertGuest();
    }

    public function testAdminCanLoginSuccessfully(): void
    {
        $admin = $this->createAdminUser([
            'email' => 'admin@example.com',
            'raw_password' => 'password123',
        ]);

        $response = $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.attendance.list'));
        $this->assertAuthenticatedAs($admin);
    }
}
