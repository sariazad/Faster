<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** آخر القائمة: the host can run the game without playing (host_plays = false). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('akher')->table('rooms', function (Blueprint $table) {
            $table->boolean('host_plays')->default(true)->after('mode');
        });
    }

    public function down(): void
    {
        Schema::connection('akher')->table('rooms', function (Blueprint $table) {
            $table->dropColumn('host_plays');
        });
    }
};
