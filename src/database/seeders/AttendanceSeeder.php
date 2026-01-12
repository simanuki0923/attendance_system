<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceBreak;
use App\Models\AttendanceTotal;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AttendanceSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first();

        if (!$user) {
            return;
        }

        $base = Carbon::today();
        $days = 7;

        for ($i = 0; $i < $days; $i++) {
            $date = $base->copy()->subDays($i)->toDateString();
            $pattern = $i % 3;

            if ($pattern === 0) {
                $start  = '09:00:00';
                $end    = '18:00:00';
                $breaks = [
                    ['break_no' => 1, 'start_time' => '12:00:00', 'end_time' => '13:00:00'],
                    ['break_no' => 2, 'start_time' => '15:00:00', 'end_time' => '15:15:00'],
                ];
                $note = 'ダミー：通常勤務(9-18)';
            } elseif ($pattern === 1) {
                $start  = '10:00:00';
                $end    = '19:00:00';
                $breaks = [
                    ['break_no' => 1, 'start_time' => '13:00:00', 'end_time' => '14:00:00'],
                ];
                $note = 'ダミー：遅番(10-19)';
            } else {
                $start  = '09:30:00';
                $end    = '17:30:00';
                $breaks = [
                    ['break_no' => 1, 'start_time' => '12:30:00', 'end_time' => '13:15:00'],
                    ['break_no' => 2, 'start_time' => '16:00:00', 'end_time' => '16:10:00'],
                ];
                $note = 'ダミー：短め勤務(9:30-17:30)';
            }

            $attendance = Attendance::updateOrCreate(
                [
                    'user_id'   => $user->id,
                    'work_date' => $date,
                ],
                [
                    'note' => $note,
                ]
            );

            AttendanceTime::updateOrCreate(
                ['attendance_id' => $attendance->id],
                ['start_time' => $start, 'end_time' => $end]
            );

            $totalBreakMinutes = 0;

            foreach ($breaks as $breakData) {
                $minutes = $this->minutesBetween($breakData['start_time'], $breakData['end_time']);
                $totalBreakMinutes += $minutes;

                AttendanceBreak::updateOrCreate(
                    [
                        'attendance_id' => $attendance->id,
                        'break_no'      => $breakData['break_no'],
                    ],
                    [
                        'start_time' => $breakData['start_time'],
                        'end_time'   => $breakData['end_time'],
                        'minutes'    => $minutes,
                    ]
                );
            }

            $workMinutes      = $this->minutesBetween($start, $end);
            $totalWorkMinutes = max($workMinutes - $totalBreakMinutes, 0);

            AttendanceTotal::updateOrCreate(
                ['attendance_id' => $attendance->id],
                [
                    'break_minutes'      => $totalBreakMinutes,
                    'total_work_minutes' => $totalWorkMinutes,
                ]
            );
        }
    }

    private function minutesBetween(string $start, string $end): int
    {
        $startTime = Carbon::createFromFormat('H:i:s', $start);
        $endTime = Carbon::createFromFormat('H:i:s', $end);
        return $startTime->diffInMinutes($endTime);
    }
}
