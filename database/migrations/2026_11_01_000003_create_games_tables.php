<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('team_a_name', 40);
            $table->string('team_b_name', 40);
            $table->integer('team_a_score')->default(0);
            $table->integer('team_b_score')->default(0);
            $table->char('current_team', 1)->default('a');
            $table->string('status', 20)->default('playing'); // playing | finished
            $table->json('helpers')->nullable();               // used helpers per team
            $table->unsignedBigInteger('current_game_question_id')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('game_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->char('team', 1);                       // the team that picked it
            $table->unsignedTinyInteger('position');       // column order on the board
        });

        Schema::create('game_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('points');
            $table->unsignedTinyInteger('slot');           // 1 or 2 (two questions per level)
            $table->string('status', 20)->default('pending'); // pending | answered
            $table->char('opened_by', 1)->nullable();      // team whose turn it was
            $table->char('answered_by', 1)->nullable();    // a | b | n (nobody)
            $table->integer('awarded_points')->default(0);
            $table->json('effects')->nullable();           // helpers applied to this question
            $table->timestamps();

            $table->unique(['game_id', 'category_id', 'points', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_questions');
        Schema::dropIfExists('game_categories');
        Schema::dropIfExists('games');
    }
};
