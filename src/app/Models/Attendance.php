<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'note',
    ];

    protected $casts = [
        'work_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function time(): HasOne
    {
        return $this->hasOne(AttendanceTime::class);
    }

    public function total(): HasOne
    {
        return $this->hasOne(AttendanceTotal::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AttendanceApplication::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class)
            ->orderBy('break_no');
    }
}
