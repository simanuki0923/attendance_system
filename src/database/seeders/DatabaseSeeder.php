<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ① 承認状態マスタ
        $this->call([
            ApplicationStatusSeeder::class,
        ]);

        // ② 管理者デモユーザー
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => '管理者デモ',
                'email'    => 'admin@example.com',
                'password' => 'admin1234',
            ]
        );
        // ★メール認証済みにする（MustVerifyEmail 対策）
        $admin->forceFill([
            'email_verified_at' => now(),
            'is_admin' => true, // usersにis_adminがある :contentReference[oaicite:4]{index=4}
        ])->save();

        // ③ 一般ユーザーデモ
        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name'     => 'Test User',
                'email'    => 'test@example.com',
                'password' => 'password123',
            ]
        );
        // ★メール認証済みにする
        $user->forceFill([
            'email_verified_at' => now(),
            'is_admin' => false,
        ])->save();

        // ④ 勤怠ダミー（あなたが作った AttendanceSeeder 呼び出し）
        $this->call([
            AttendanceSeeder::class,
        ]);
    }
}
