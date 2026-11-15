<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** آخر القائمة: several lists played back to back, the grand total decides the winner. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('akher')->table('rooms', function (Blueprint $table) {
            $table->json('list_queue')->nullable()->after('list_id');      // list ids in play order
            $table->unsignedSmallInteger('list_idx')->default(0)->after('list_queue');
            $table->json('series_results')->nullable()->after('scores');  // [{list_id, title, scores}] per finished list
        });
    }

    public function down(): void
    {
        Schema::connection('akher')->table('rooms', function (Blueprint $table) {
            $table->dropColumn(['list_queue', 'list_idx', 'series_results']);
        });
    }
};
