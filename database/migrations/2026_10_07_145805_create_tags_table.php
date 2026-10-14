<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->string('color', 20); // ключ из Tag::COLORS
            $table->timestamps();
        });

        Schema::create('tag_task', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->primary(['tag_id', 'task_id']);
        });

        $now = now();
        DB::table('tags')->insert([
            ['name' => 'Работа', 'color' => 'blue', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Личное', 'color' => 'green', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Здоровье', 'color' => 'red', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_task');
        Schema::dropIfExists('tags');
    }
};
