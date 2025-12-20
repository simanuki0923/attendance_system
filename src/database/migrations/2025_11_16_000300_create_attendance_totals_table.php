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
            $table->foreignId('attendance_id')
                ->constrained('attendances')
                ->cascadeOnDelete();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('total_work_minutes')->default(0);
            $table->timestamps();
            $table->unique('attendance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_totals');
    }
};
