<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_venues', function (Blueprint $table) {
            $table->foreignId('supervisor_user_id')->nullable()->after('pic_user_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('competition_sessions', function (Blueprint $table) {
            $table->foreignId('pic_user_id')->nullable()->after('venue_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_user_id')->nullable()->after('pic_user_id')
                ->constrained('users')->nullOnDelete();
        });

        DB::table('competition_sessions')->orderBy('id')->get()->each(function ($session) {
            $venue = $session->venue_id ? DB::table('competition_venues')->where('id', $session->venue_id)->first() : null;
            DB::table('competition_sessions')->where('id', $session->id)->update([
                'pic_user_id' => $venue?->pic_user_id,
                'supervisor_user_id' => $venue?->supervisor_user_id,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('competition_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_user_id');
            $table->dropConstrainedForeignId('pic_user_id');
        });
        Schema::table('competition_venues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_user_id');
        });
    }
};
