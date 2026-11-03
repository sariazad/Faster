<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Chosen on the setup page; older games keep the previous fixed values.
            $table->unsignedSmallInteger('answer_seconds')->default(60);
            $table->unsignedSmallInteger('steal_seconds')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['answer_seconds', 'steal_seconds']);
        });
    }
};
