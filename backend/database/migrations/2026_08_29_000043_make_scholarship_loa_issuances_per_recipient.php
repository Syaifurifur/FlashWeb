<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholarship_loa_issuances', function (Blueprint $table) {
            $table->index('competition_result_id', 'scholarship_loa_result_foreign_index');
        });
        Schema::table('scholarship_loa_issuances', function (Blueprint $table) {
            $table->dropUnique(['competition_result_id']);
            $table->string('recipient_key', 80)->default('legacy')->after('competition_result_id');
            $table->unique(['competition_result_id', 'recipient_key'], 'scholarship_loa_result_recipient_unique');
        });
    }

    public function down(): void
    {
        $keepIds = DB::table('scholarship_loa_issuances')
            ->selectRaw('MIN(id) as id')
            ->groupBy('competition_result_id')
            ->pluck('id');

        if ($keepIds->isNotEmpty()) {
            DB::table('scholarship_loa_issuances')->whereNotIn('id', $keepIds)->delete();
        }

        Schema::table('scholarship_loa_issuances', function (Blueprint $table) {
            $table->dropUnique('scholarship_loa_result_recipient_unique');
            $table->dropColumn('recipient_key');
            $table->unique('competition_result_id');
        });
        Schema::table('scholarship_loa_issuances', function (Blueprint $table) {
            $table->dropIndex('scholarship_loa_result_foreign_index');
        });
    }
};
