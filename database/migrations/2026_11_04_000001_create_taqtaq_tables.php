<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «طق طق»: a hex board where each team connects its two sides.
 * Classic games keep working unchanged (mode = classic).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('mode', 20)->default('classic');           // classic | taqtaq
            $table->unsignedTinyInteger('board_size')->nullable();    // taqtaq: N×N
            $table->char('winner', 1)->nullable();                    // taqtaq: a | b
            $table->json('winning_path')->nullable();                 // taqtaq: board_cell ids
            $table->unsignedBigInteger('current_cell_question_id')->nullable();
        });

        Schema::create('board_cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('row');
            $table->unsignedTinyInteger('col');
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->char('owner', 1)->nullable();                     // a | b | null (free)
            $table->boolean('protected')->default(false);             // «احمِها»
            $table->timestamps();

            $table->unique(['game_id', 'row', 'col']);
        });

        // Every question shown on a cell, so a game never repeats one.
        Schema::create('cell_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_cell_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->char('opened_by', 1);
            $table->char('answered_by', 1)->nullable();               // a | b | n (nobody) | x (changed / cancelled)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cell_questions');
        Schema::dropIfExists('board_cells');

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['mode', 'board_size', 'winner', 'winning_path', 'current_cell_question_id']);
        });
    }
};
