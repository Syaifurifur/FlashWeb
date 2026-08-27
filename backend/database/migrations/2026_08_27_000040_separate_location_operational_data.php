<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_sessions', function (Blueprint $table) {
            $table->timestamp('judging_locked_at')->nullable()->after('schedule_venues');
            $table->timestamp('results_announced_at')->nullable()->after('judging_locked_at');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->index(['competition_session_id', 'status'], 'registrations_session_status_index');
        });

        Schema::table('competition_notifications', function (Blueprint $table) {
            $table->index(['competition_session_id', 'published_at'], 'notifications_session_published_index');
        });

        DB::table('competition_sessions')->orderBy('id')->get()->each(function ($session) {
            $competition = DB::table('competitions')->where('id', $session->competition_id)->first();
            if (! $competition) return;

            DB::table('competition_sessions')->where('id', $session->id)->update([
                'judging_locked_at'=>$competition->judging_locked_at,
                'results_announced_at'=>$competition->results_announced_at,
            ]);
        });

        DB::table('competition_results')->whereNull('competition_session_id')->orderBy('id')->get()->each(function ($result) {
            $sessionId = DB::table('registrations')->where('id', $result->registration_id)->value('competition_session_id');
            if ($sessionId) {
                DB::table('competition_results')->where('id', $result->id)->update(['competition_session_id'=>$sessionId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('competition_notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_session_published_index');
        });
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex('registrations_session_status_index');
        });
        Schema::table('competition_sessions', function (Blueprint $table) {
            $table->dropColumn(['judging_locked_at', 'results_announced_at']);
        });
    }
};
