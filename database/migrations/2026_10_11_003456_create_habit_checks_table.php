<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habit_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->string('date', 10);             // «Y-m-d» строкой: сравнение без часовых хвостов
            $table->unsignedSmallInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['habit_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_checks');
    }
};
