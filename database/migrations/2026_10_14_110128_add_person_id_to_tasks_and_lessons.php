<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * person_id = null — это свои задачи и пары; иначе — график другого человека.
     */
    public function up(): void
    {
        foreach (['tasks', 'lessons'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('person_id')->nullable()->constrained('people')->cascadeOnDelete();
                $table->index('person_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['tasks', 'lessons'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('person_id');
            });
        }
    }
};
