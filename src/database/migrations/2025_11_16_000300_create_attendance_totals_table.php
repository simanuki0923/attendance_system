<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_totals', function (Blueprint $table) {
            $table->id();

            // 親の勤怠レコード
            $table->foreignId('attendance_id')
                ->constrained('attendances')
                ->cascadeOnDelete();

            // 休憩時間（分単位）例: 90分なら 90
            $table->unsignedInteger('break_minutes')->default(0);

            // 合計勤務時間（分単位）
            $table->unsignedInteger('total_work_minutes')->default(0);

            $table->timestamps();

            // 勤怠1日につき集計レコードは1件
            $table->unique('attendance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_totals');
    }
};
