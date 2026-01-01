<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceApplication extends Model
{
    protected $fillable = [
        'attendance_id',
        'applicant_user_id',
        'status_id',
        'reason',
        'applied_at',

        'requested_work_start_time',
        'requested_work_end_time',
        'requested_break1_start_time',
        'requested_break1_end_time',
        'requested_break2_start_time',
        'requested_break2_end_time',
        'requested_note',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }

    public function status()
    {
        return $this->belongsTo(ApplicationStatus::class, 'status_id');
    }
}
