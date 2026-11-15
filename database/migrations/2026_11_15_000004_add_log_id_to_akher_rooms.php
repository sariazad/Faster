<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('akher')->table('rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('log_id')->nullable(); // play_logs row of the game in progress
        });
    }

    public function down(): void
    {
        Schema::connection('akher')->table('rooms', function (Blueprint $table) {
            $table->dropColumn('log_id');
        });
    }
};
