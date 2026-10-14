<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday'); // 1 — понедельник … 7 — воскресенье
            $table->string('starts_at', 5);         // "HH:MM"
            $table->string('ends_at', 5);
            $table->string('subject', 100);
            $table->string('kind', 20)->nullable(); // ключ из Lesson::KINDS
            $table->string('room', 30)->nullable();
            $table->string('teacher', 100)->nullable();
            $table->string('weeks', 4)->default('all'); // all | odd | even
            $table->timestamps();

            $table->index(['weekday', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
