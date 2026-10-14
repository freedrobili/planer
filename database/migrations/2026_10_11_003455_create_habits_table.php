<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('color', 20);            // ключ из Tag::COLORS
            $table->json('weekdays');               // дни недели, когда привычка нужна: [1..7]
            $table->unsignedTinyInteger('target')->default(1); // сколько раз в день; 1 — просто отметка
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habits');
    }
};
