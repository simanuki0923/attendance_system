<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_times', function (Blueprint $table) {
            $table->id();

            // 親の勤怠レコード
            $table->foreignId('attendance_id')
                ->constrained('attendances')
                ->cascadeOnDelete();

            // 出勤時間 / 退勤時間（どちらも後から埋まる可能性があるので nullable）
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->timestamps();

            // 勤怠1日につき時間レコードは1件
            $table->unique('attendance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_times');
    }
};
