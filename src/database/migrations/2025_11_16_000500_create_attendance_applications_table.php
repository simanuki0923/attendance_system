<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_applications', function (Blueprint $table) {
            $table->id();

            // 対象となる勤怠レコード
            $table->foreignId('attendance_id')
                ->constrained('attendances')
                ->cascadeOnDelete();

            // 申請者（一般ユーザー）
            $table->foreignId('applicant_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // 承認状態（申請中 / 承認 / 却下）
            $table->foreignId('status_id')
                ->constrained('application_statuses');

            // 申請理由
            $table->text('reason');

            // 申請日時
            $table->dateTime('applied_at');

            // ★追加：修正後の希望打刻内容（承認前はここにのみ保持）
            $table->time('requested_work_start_time')->nullable();
            $table->time('requested_work_end_time')->nullable();
            $table->time('requested_break1_start_time')->nullable();
            $table->time('requested_break1_end_time')->nullable();
            $table->time('requested_break2_start_time')->nullable();
            $table->time('requested_break2_end_time')->nullable();
            $table->text('requested_note')->nullable();

            $table->timestamps();

            // よく使いそうな組み合わせでインデックス
            $table->index(['status_id', 'applied_at']);
            $table->index(['applicant_user_id', 'applied_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_applications');
    }
};
