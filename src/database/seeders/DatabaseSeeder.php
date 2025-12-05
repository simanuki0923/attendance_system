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

        // ② 管理者デモユーザー（passwordは平文でOK）
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => '管理者デモ',
                'email'    => 'admin@example.com',
                'password' => 'admin1234', // ★Hash::makeしない
            ]
        );

        // ③ 一般ユーザーデモ（現状のTest Userを維持）
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name'     => 'Test User',
                'email'    => 'test@example.com',
                'password' => 'password123', // ★平文
            ]
        );

        // User::factory(10)->create(); は必要なら復活
    }
}
