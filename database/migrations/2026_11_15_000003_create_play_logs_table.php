<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statistics: one row per played session of the games that don't keep their own history
 * (آخر القائمة rooms are deleted after a few hours, ألف ياء team games live in the browser).
 * مين الأسبق / طق طق are read from the games table and the ألف ياء challenge from its scores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_logs', function (Blueprint $table) {
            $table->id();
            $table->string('game', 20)->index();            // akher | alefya
            $table->string('ref', 64)->nullable();          // client session id (ألف ياء)
            $table->timestamp('started_at')->index();
            $table->timestamp('last_at')->nullable();       // last activity (unfinished sessions)
            $table->timestamp('ended_at')->nullable();
            $table->boolean('finished')->default(false);
            $table->unsignedSmallInteger('players')->nullable();
            $table->unsignedSmallInteger('rounds')->nullable();
            $table->string('winner', 60)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['game', 'ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_logs');
    }
};
