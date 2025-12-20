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
            $table->foreignId('attendance_id')
                ->constrained('attendances')
                ->cascadeOnDelete();
            $table->foreignId('applicant_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('status_id')
                ->constrained('application_statuses');
            $table->text('reason');
            $table->dateTime('applied_at');
            $table->time('requested_work_start_time')->nullable();
            $table->time('requested_work_end_time')->nullable();
            $table->time('requested_break1_start_time')->nullable();
            $table->time('requested_break1_end_time')->nullable();
            $table->time('requested_break2_start_time')->nullable();
            $table->time('requested_break2_end_time')->nullable();
            $table->text('requested_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_applications');
    }
};
