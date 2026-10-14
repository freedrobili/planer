<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('date');
            $table->string('time', 5)->nullable(); // "HH:MM", null — задача без времени
            $table->boolean('done')->default(false);
            $table->timestamps();

            $table->index(['date', 'time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
