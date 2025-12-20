<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'applicant_user_id',
        'status_id',
        'reason',
        'applied_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ApplicationStatus::class, 'status_id');
    }
}
