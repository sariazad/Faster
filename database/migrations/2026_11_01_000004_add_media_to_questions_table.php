<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('audio')->nullable();          // sound played with the question
            $table->string('answer_image')->nullable();   // picture shown with the answer
            $table->string('answer_audio')->nullable();   // sound played with the answer
        });

        // The answer can now be a picture or a sound only.
        Schema::table('questions', function (Blueprint $table) {
            $table->string('answer', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['audio', 'answer_image', 'answer_audio']);
        });
    }
};
