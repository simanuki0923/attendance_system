<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            ApplicationStatusSeeder::class,
        ]);

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => '管理者ユーザー',
                'email'    => 'admin@example.com',
                'password' => Hash::make('admin1234'),
            ]
        );
        $admin->forceFill([
            'email_verified_at' => now(),
            'is_admin' => true,
        ])->save();

        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name'     => '一般ユーザー',
                'email'    => 'test@example.com',
                'password' => Hash::make('password123'),
            ]
        );
        $user->forceFill([
            'email_verified_at' => now(),
            'is_admin' => false,
        ])->save();

        $this->call([
            AttendanceSeeder::class,
        ]);
    }
}
