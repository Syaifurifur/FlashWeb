<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('best_of_sets')->nullable()->after('score_b');
            $table->json('set_scores')->nullable()->after('best_of_sets');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropColumn(['best_of_sets', 'set_scores']);
        });
    }
};
