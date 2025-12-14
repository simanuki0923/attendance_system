<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ApplicationStatus;

class ApplicationStatusSeeder extends Seeder
{
    public function run(): void
    {
        ApplicationStatus::updateOrCreate(
            ['code' => ApplicationStatus::CODE_PENDING],
            ['label' => '承認待ち', 'sort_no' => 1]
        );

        ApplicationStatus::updateOrCreate(
            ['code' => ApplicationStatus::CODE_APPROVED],
            ['label' => '承認済み', 'sort_no' => 2]
        );

        ApplicationStatus::updateOrCreate(
            ['code' => ApplicationStatus::CODE_REJECTED],
            ['label' => '却下', 'sort_no' => 3]
        );
    }
}
