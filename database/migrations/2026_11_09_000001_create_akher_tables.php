<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * آخر القائمة — every table lives on the separate "akher" connection (database/akher.sqlite).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('akher');

        $schema->create('lists', function (Blueprint $table) {
            $table->string('id', 40)->primary();
            $table->string('title_ar');
            $table->string('extra_label')->nullable();
            $table->string('unit_hint')->nullable();
            $table->string('source')->nullable();
            $table->string('updated')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        $schema->create('list_items', function (Blueprint $table) {
            $table->id();
            $table->string('list_id', 40);
            $table->unsignedSmallInteger('rank');
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->json('aliases')->nullable();
            $table->string('extra')->nullable();
            $table->unique(['list_id', 'rank']);
            $table->foreign('list_id')->references('id')->on('lists')->cascadeOnDelete()->cascadeOnUpdate();
        });

        $schema->create('rooms', function (Blueprint $table) {
            $table->string('code', 4)->primary();
            $table->string('host_device', 64);
            $table->string('status', 10)->default('lobby');
            $table->string('list_id', 40)->nullable();
            $table->unsignedTinyInteger('turns_per_player')->default(2);
            $table->unsignedSmallInteger('turn_seconds')->default(30);
            $table->boolean('wrong_penalty')->default(false);
            $table->json('order')->nullable();
            $table->unsignedSmallInteger('turn_idx')->default(0);
            $table->timestamp('turn_deadline', 3)->nullable();
            $table->json('scores')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedSmallInteger('round')->default(1);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        $schema->create('players', function (Blueprint $table) {
            $table->id();
            $table->string('room_code', 4)->index();
            $table->string('name', 20);
            $table->char('team', 1);
            $table->string('device_id', 64)->nullable();
            $table->boolean('spectator')->default(false);
            $table->timestamp('joined_at')->useCurrent();
            $table->foreign('room_code')->references('code')->on('rooms')->cascadeOnDelete();
        });

        $schema->create('guesses', function (Blueprint $table) {
            $table->id();
            $table->string('room_code', 4)->index();
            $table->unsignedSmallInteger('round')->default(1);
            $table->unsignedSmallInteger('turn_idx');
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('player_name', 20);
            $table->char('team', 1);
            $table->string('text', 80);
            $table->string('result', 10);
            $table->unsignedSmallInteger('item_rank')->nullable();
            $table->smallInteger('points')->default(0);
            $table->timestamp('created_at', 3)->useCurrent();
            $table->foreign('room_code')->references('code')->on('rooms')->cascadeOnDelete();
        });

        // An item can only be taken once per round.
        DB::connection('akher')->statement(
            "CREATE UNIQUE INDEX guesses_one_match_per_item ON guesses (room_code, round, item_rank) WHERE result = 'match'"
        );
    }

    public function down(): void
    {
        $schema = Schema::connection('akher');

        foreach (['guesses', 'players', 'rooms', 'list_items', 'lists'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
