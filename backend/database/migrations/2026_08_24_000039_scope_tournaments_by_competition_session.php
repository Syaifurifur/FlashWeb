<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('competition_sessions', 'schedule_venues')) Schema::table('competition_sessions', function (Blueprint $table) {
            $table->json('schedule_venues')->nullable()->after('timeline');
        });
        if (! Schema::hasColumn('tournament_draws', 'competition_session_id')) {
            // MySQL sebelumnya memakai indeks unik competition_id+version sebagai penopang foreign key competition_id.
            // Sediakan indeks mandiri sebelum indeks unik lama diganti dengan cakupan sesi kota.
            Schema::table('tournament_draws', fn (Blueprint $table) => $table->index('competition_id', 'tournament_draws_competition_id_index'));
            Schema::table('tournament_draws', function (Blueprint $table) {
                $table->dropUnique(['competition_id', 'version']);
                $table->foreignId('competition_session_id')->nullable()->after('competition_id')
                    ->constrained('competition_sessions')->nullOnDelete();
                $table->unique(['competition_id', 'competition_session_id', 'version'], 'tournament_draws_scope_version_unique');
            });
        }
        if (! Schema::hasColumn('tournament_schedule_blocks', 'competition_session_id')) Schema::table('tournament_schedule_blocks', function (Blueprint $table) {
            $table->foreignId('competition_session_id')->nullable()->after('competition_id')
                ->constrained('competition_sessions')->nullOnDelete();
            $table->index(['competition_session_id', 'starts_at'], 'tsb_session_starts_index');
        });
        if (! Schema::hasColumn('competition_notifications', 'competition_session_id')) Schema::table('competition_notifications', function (Blueprint $table) {
            $table->foreignId('competition_session_id')->nullable()->after('competition_id')
                ->constrained('competition_sessions')->nullOnDelete();
        });

        DB::table('competition_sessions')->orderBy('id')->get()->each(function ($session) {
            $venues = DB::table('competitions')->where('id', $session->competition_id)->value('schedule_venues');
            if ($venues) DB::table('competition_sessions')->where('id', $session->id)->update(['schedule_venues' => $venues]);
        });

        DB::table('tournament_draws')->orderBy('id')->get()->each(function ($draw) {
            $sessionIds = DB::table('tournament_draw_entries')
                ->join('registrations', 'registrations.id', '=', 'tournament_draw_entries.registration_id')
                ->where('tournament_draw_entries.tournament_draw_id', $draw->id)
                ->whereNotNull('registrations.competition_session_id')
                ->distinct()->pluck('registrations.competition_session_id');
            if ($sessionIds->count() !== 1) {
                $sessionIds = DB::table('competition_sessions')->where('competition_id', $draw->competition_id)->pluck('id');
            }
            if ($sessionIds->count() === 1) {
                DB::table('tournament_draws')->where('id', $draw->id)->update(['competition_session_id' => $sessionIds->first()]);
            }
        });

        DB::table('tournament_schedule_blocks')->whereNotNull('tournament_draw_id')->orderBy('id')->get()->each(function ($block) {
            $sessionId = DB::table('tournament_draws')->where('id', $block->tournament_draw_id)->value('competition_session_id');
            if ($sessionId) DB::table('tournament_schedule_blocks')->where('id', $block->id)->update(['competition_session_id' => $sessionId]);
        });
    }

    public function down(): void
    {
        Schema::table('competition_notifications', fn (Blueprint $table) => $table->dropConstrainedForeignId('competition_session_id'));
        Schema::table('tournament_schedule_blocks', function (Blueprint $table) {
            $table->dropIndex('tsb_session_starts_index');
            $table->dropConstrainedForeignId('competition_session_id');
        });
        Schema::table('tournament_draws', function (Blueprint $table) {
            $table->dropUnique('tournament_draws_scope_version_unique');
            $table->dropConstrainedForeignId('competition_session_id');
            $table->unique(['competition_id', 'version']);
            $table->dropIndex('tournament_draws_competition_id_index');
        });
        Schema::table('competition_sessions', fn (Blueprint $table) => $table->dropColumn('schedule_venues'));
    }
};
