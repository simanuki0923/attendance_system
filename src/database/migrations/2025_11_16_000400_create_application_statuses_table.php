<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('label', 50);
            $table->unsignedInteger('sort_no')->default(1);
            $table->timestamps();
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_statuses');
    }
};
