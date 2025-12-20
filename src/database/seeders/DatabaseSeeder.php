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
        $this->call([
            ApplicationStatusSeeder::class,
        ]);

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => '管理者デモ',
                'email'    => 'admin@example.com',
                'password' => 'admin1234',
            ]
        );
        $admin->forceFill([
            'email_verified_at' => now(),
            'is_admin' => true,
        ])->save();

        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name'     => 'Test User',
                'email'    => 'test@example.com',
                'password' => 'password123',
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
