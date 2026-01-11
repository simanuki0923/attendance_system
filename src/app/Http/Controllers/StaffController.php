<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffController extends Controller
{
    public function index(): View
    {
        $adminEmails = (array) config('admin.emails', []);

        $staffUsers = User::query()
            ->when(! empty($adminEmails), function ($query) use ($adminEmails): void {
                $query->whereNotIn('email', $adminEmails);
            })
            ->orderBy('name')
            ->get();

        $staffList = $staffUsers->map(function (User $user): array {
            return [
                'name_label'  => $user->name,
                'email_label' => $user->email,
                'detail_url'  => route('admin.attendance.staff', ['id' => $user->id]),
                'detail_text' => '詳細',
            ];
        });

        return view('admin.staff', [
            'pageTitle' => 'スタッフ一覧',
            'staffList' => $staffList,
        ]);
    }

    public function attendance(Request $request, int $id): View
    {
        $staffUser = User::findOrFail($id);

        $rawMonth = $request->query('month');

        if ($rawMonth) {
            try {
                $targetMonth = Carbon::createFromFormat('Y-m', (string) $rawMonth)->startOfMonth();
            } catch (\Throwable $throwable) {
                $targetMonth = Carbon::today()->startOfMonth();
            }
        } else {
            $targetMonth = Carbon::today()->startOfMonth();
        }

        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth = $targetMonth->copy()->endOfMonth();

        $recordsByDate = Attendance::with(['time', 'total', 'breaks'])
            ->where('user_id', $staffUser->id)
            ->whereBetween('work_date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(function (Attendance $attendance): string {
                return Carbon::parse($attendance->work_date)->format('Y-m-d');
            });

        $formatMinutes = function (?int $minutes): string {
            $totalMinutes = (int) ($minutes ?? 0);

            $hours = intdiv($totalMinutes, 60);
            $remainingMinutes = $totalMinutes % 60;

            return $hours . ':' . str_pad((string) $remainingMinutes, 2, '0', STR_PAD_LEFT);
        };

        $attendances = collect();
        $cursorDate = $startOfMonth->copy();

        while ($cursorDate->lte($endOfMonth)) {
            $dateKey = $cursorDate->format('Y-m-d');
            $attendance = $recordsByDate->get($dateKey);

            $weekdayJa = $cursorDate->locale('ja')->isoFormat('ddd');

            if ($attendance) {
                $attendanceTime = $attendance->time;

                $startLabel = $attendanceTime?->start_time
                    ? Carbon::parse($attendanceTime->start_time)->format('H:i')
                    : '';

                $endLabel = $attendanceTime?->end_time
                    ? Carbon::parse($attendanceTime->end_time)->format('H:i')
                    : '';

                $attendanceTotal = $attendance->total;
                $breakLabel = $formatMinutes($attendanceTotal?->break_minutes);
                $workLabel = $formatMinutes($attendanceTotal?->total_work_minutes);

                $detailUrl = route('admin.attendance.detail', ['id' => $attendance->id]);
            } else {
                $startLabel = '';
                $endLabel = '';
                $breakLabel = '';
                $workLabel = '';
                $detailUrl = null;
            }

            $attendances->push([
                'date_label'  => $cursorDate->format('m/d') . '(' . $weekdayJa . ')',
                'start_label' => $startLabel,
                'end_label'   => $endLabel,
                'break_label' => $breakLabel,
                'total_label' => $workLabel,
                'detail_url'  => $detailUrl,
                'is_active'   => $cursorDate->isToday(),
            ]);

            $cursorDate->addDay();
        }

        $prevMonth = $targetMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $targetMonth->copy()->addMonth()->format('Y-m');

        return view('admin.staff_id', [
            'staffNameLabel'    => $staffUser->name,
            'currentMonthLabel' => $targetMonth->format('Y/m'),
            'prevMonthUrl'      => route('admin.attendance.staff', [
                'id'    => $staffUser->id,
                'month' => $prevMonth,
            ]),
            'nextMonthUrl'      => route('admin.attendance.staff', [
                'id'    => $staffUser->id,
                'month' => $nextMonth,
            ]),
            'attendances'       => $attendances,
            'csvDownloadUrl'    => route('admin.attendance.staff.csv', [
                'id'    => $staffUser->id,
                'month' => $targetMonth->format('Y-m'),
            ]),
        ]);
    }

    public function attendanceCsv(Request $request, int $id): StreamedResponse
    {
        $staffUser = User::findOrFail($id);

        $rawMonth = $request->query('month');

        if ($rawMonth) {
            try {
                $targetMonth = Carbon::createFromFormat('Y-m', (string) $rawMonth)->startOfMonth();
            } catch (\Throwable $throwable) {
                $targetMonth = Carbon::today()->startOfMonth();
            }
        } else {
            $targetMonth = Carbon::today()->startOfMonth();
        }

        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth = $targetMonth->copy()->endOfMonth();

        $recordsByDate = Attendance::with(['time', 'total'])
            ->where('user_id', $staffUser->id)
            ->whereBetween('work_date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(function (Attendance $attendance): string {
                return Carbon::parse($attendance->work_date)->format('Y-m-d');
            });

        $formatMinutes = function (?int $minutes): string {
            $totalMinutes = (int) ($minutes ?? 0);

            if ($totalMinutes === 0) {
                return '';
            }

            $hours = intdiv($totalMinutes, 60);
            $remainingMinutes = $totalMinutes % 60;

            return $hours . ':' . str_pad((string) $remainingMinutes, 2, '0', STR_PAD_LEFT);
        };

        $rows = [];
        $rows[] = ['日付', '出勤', '退勤', '休憩', '合計'];

        $cursorDate = $startOfMonth->copy();

        while ($cursorDate->lte($endOfMonth)) {
            $dateKey = $cursorDate->format('Y-m-d');
            $attendance = $recordsByDate->get($dateKey);

            $weekdayJa = $cursorDate->locale('ja')->isoFormat('ddd');
            $dateLabel = $cursorDate->format('m/d') . '(' . $weekdayJa . ')';

            if ($attendance) {
                $attendanceTime = $attendance->time;

                $startLabel = $attendanceTime?->start_time
                    ? Carbon::parse($attendanceTime->start_time)->format('H:i')
                    : '';

                $endLabel = $attendanceTime?->end_time
                    ? Carbon::parse($attendanceTime->end_time)->format('H:i')
                    : '';

                $attendanceTotal = $attendance->total;

                $breakLabel = $formatMinutes($attendanceTotal?->break_minutes);
                $workLabel = $formatMinutes($attendanceTotal?->total_work_minutes);
            } else {
                $startLabel = '';
                $endLabel = '';
                $breakLabel = '';
                $workLabel = '';
            }

            $rows[] = [$dateLabel, $startLabel, $endLabel, $breakLabel, $workLabel];
            $cursorDate->addDay();
        }

        $fileName = sprintf('%s_%s_勤怠一覧.csv', $targetMonth->format('Y-m'), $staffUser->name);

        return response()->streamDownload(
            function () use ($rows): void {
                $handle = fopen('php://output', 'w');

                foreach ($rows as $row) {
                    $convertedRow = array_map(
                        function (mixed $value): string {
                            return mb_convert_encoding((string) $value, 'SJIS-win', 'UTF-8');
                        },
                        $row
                    );

                    fputcsv($handle, $convertedRow);
                }

                fclose($handle);
            },
            $fileName,
            ['Content-Type' => 'text/csv; charset=Shift_JIS']
        );
    }
}
