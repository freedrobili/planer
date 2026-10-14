<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('ends_at', 5)->nullable()->after('time');
            $table->text('description')->nullable()->after('title');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['ends_at', 'description']);
        });
    }
};
