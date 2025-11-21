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

            // どの勤怠に対する申請か
            $table->foreignId('attendance_id')
                ->constrained('attendances')
                ->cascadeOnDelete();

            // 誰が申請したか（名前は users から取得）
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
