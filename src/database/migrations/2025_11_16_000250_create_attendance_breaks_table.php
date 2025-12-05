<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_breaks', function (Blueprint $table) {
            $table->id();

            // 親の勤怠レコード
            $table->foreignId('attendance_id')
                ->constrained('attendances')
                ->cascadeOnDelete();

            // 休憩の何回目か（1,2,3...）
            $table->unsignedTinyInteger('break_no');

            // 休憩入り / 休憩戻り時刻
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // その休憩の分数（start/end 両方ある時に計算して入れる）
            $table->unsignedInteger('minutes')->default(0);

            $table->timestamps();

            // 1勤怠につき同じ break_no は1件だけ
            $table->unique(['attendance_id', 'break_no']);
            $table->index(['attendance_id', 'break_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_breaks');
    }
};
