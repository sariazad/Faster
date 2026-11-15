<?php

use App\Support\TextKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** ألف ياء: a normalised copy of the answer, so the same answer isn't served twice in a round. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('alefya')->table('questions', function (Blueprint $table) {
            $table->string('answer_key')->nullable()->after('answer');
            $table->index(['letter', 'times_used']);
            $table->index('answer_key');
        });

        $db = DB::connection('alefya');
        $db->transaction(function () use ($db) {
            foreach ($db->table('questions')->select('id', 'answer')->cursor() as $row) {
                $db->table('questions')->where('id', $row->id)->update(['answer_key' => TextKey::make($row->answer)]);
            }
        });
    }

    public function down(): void
    {
        Schema::connection('alefya')->table('questions', function (Blueprint $table) {
            $table->dropIndex(['letter', 'times_used']);
            $table->dropIndex(['answer_key']);
            $table->dropColumn('answer_key');
        });
    }
};
