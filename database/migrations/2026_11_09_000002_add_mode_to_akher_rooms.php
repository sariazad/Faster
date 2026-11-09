<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** آخر القائمة: "teams" (two equal teams) or "solo" (every player for themself). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('akher')->table('rooms', function (Blueprint $table) {
            $table->string('mode', 10)->default('teams')->after('status');
        });
    }

    public function down(): void
    {
        Schema::connection('akher')->table('rooms', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
