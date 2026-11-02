<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // How many games this question has been drawn into, and when it was last drawn.
            // Used to rotate through the bank so questions don't come back before the others are used.
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamp('last_used_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['times_used', 'last_used_at']);
        });
    }
};
