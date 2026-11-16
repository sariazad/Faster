<?php

use Database\Seeders\QaddahaSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قدّها؟ — every table lives on the separate "qaddaha" connection (database/qaddaha.sqlite).
 * The original question bank is loaded right away so the game is playable after `migrate`.
 */
return new class extends Migration
{
    protected $connection = 'qaddaha';

    public function up(): void
    {
        $schema = Schema::connection('qaddaha');

        $schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 80);
            $table->string('icon', 16)->nullable();
            $table->unsignedSmallInteger('sort')->default(50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->unsignedSmallInteger('level')->index();      // 200 | 400 | 600
            $table->string('text', 255);
            $table->json('answers');                            // model answers shown to the judge
            $table->unsignedInteger('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['category_id', 'text']);
        });

        // Multi-device games: the host's browser is the only writer of the state.
        $schema->create('rooms', function (Blueprint $table) {
            $table->string('code', 6)->primary();
            $table->string('host_token', 64);                   // sha256 of the host's secret
            $table->json('state')->nullable();                  // full state (host only: answers, secret bets)
            $table->json('public')->nullable();                 // what the phones may see
            $table->unsignedInteger('version')->default(0);
            $table->string('seat0', 64)->nullable();            // device ids of the two team leaders
            $table->string('seat1', 64)->nullable();
            $table->boolean('finished')->default(false);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        // Phones never touch the state: they queue intents that the host validates and applies.
        $schema->create('intents', function (Blueprint $table) {
            $table->id();
            $table->string('room_code', 6)->index();
            $table->unsignedTinyInteger('team');
            $table->json('payload');
            $table->timestamp('created_at')->nullable();
        });

        (new QaddahaSeeder)->run();
    }

    public function down(): void
    {
        $schema = Schema::connection('qaddaha');
        $schema->dropIfExists('intents');
        $schema->dropIfExists('rooms');
        $schema->dropIfExists('questions');
        $schema->dropIfExists('categories');
    }
};
