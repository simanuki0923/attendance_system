<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            // どのユーザーの勤怠か
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // 勤怠日付（表示時に YYYY/MM/DD に整形）
            $table->date('work_date');

            // 備考情報
            $table->text('note')->nullable();

            $table->timestamps();

            // 1ユーザー1日1レコードに制限
            $table->unique(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
