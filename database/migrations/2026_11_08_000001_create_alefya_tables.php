<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ألف ياء — every table lives on the separate "alefya" connection (database/alefya.sqlite).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('alefya');

        $schema->create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('letter', 4)->index();
            $table->text('question');
            $table->string('answer');
            $table->json('options')->nullable();
            $table->string('category')->nullable();
            $table->string('contributor')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('times_used')->default(0);
            $table->unsignedInteger('rated_easy')->default(0);
            $table->unsignedInteger('rated_medium')->default(0);
            $table->unsignedInteger('rated_hard')->default(0);
            $table->timestamps();
        });

        $schema->create('contributions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('letter', 4);
            $table->text('body');
            $table->string('answer');
            $table->string('status', 12)->default('pending')->index();
            $table->foreignId('question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->timestamps();
        });

        $schema->create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });

        $schema->create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('pin')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        $schema->create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('won')->default(false);
            $table->unsignedSmallInteger('correct')->default(0);
            $table->unsignedSmallInteger('wrong')->default(0);
            $table->unsignedSmallInteger('time_left')->default(0);
            $table->timestamps();
        });

        $schema->create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('alefya');

        foreach (['notifications', 'scores', 'accounts', 'notes', 'contributions', 'questions'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
