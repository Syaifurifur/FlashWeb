<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_venues', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('id');
            $table->date('activity_start_date')->nullable()->after('address');
            $table->date('activity_end_date')->nullable()->after('activity_start_date');
            $table->string('field_photo_url', 1000)->nullable()->after('activity_end_date');
            $table->foreignId('pic_user_id')->nullable()->after('field_photo_url')
                ->constrained('users')->nullOnDelete();
        });

        DB::table('competition_venues')->orderBy('id')->get()->each(function ($venue) {
            $base = Str::slug($venue->city) ?: 'kota-'.$venue->id;
            $slug = $base;
            $counter = 2;
            while (DB::table('competition_venues')->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$counter++;
            }
            $session = DB::table('competition_sessions')->where('venue_id', $venue->id)->first();
            $competition = $session ? DB::table('competitions')->where('id', $session->competition_id)->first() : null;
            $pic = $competition ? DB::table('users')->where('role', 'pic')->where('competition_id', $competition->id)->first() : null;
            DB::table('competition_venues')->where('id', $venue->id)->update([
                'slug' => $slug,
                'activity_start_date' => DB::table('competition_sessions')->where('venue_id', $venue->id)->min('activity_start_date'),
                'activity_end_date' => DB::table('competition_sessions')->where('venue_id', $venue->id)->max('activity_end_date'),
                'field_photo_url' => $competition?->poster_url,
                'pic_user_id' => $pic?->id,
            ]);
        });

        Schema::table('competition_sessions', function (Blueprint $table) {
            $table->decimal('fee', 12, 2)->default(0)->after('quota');
            $table->dateTime('team_update_deadline_at')->nullable()->after('fee');
            $table->dateTime('submission_start_at')->nullable()->after('team_update_deadline_at');
            $table->dateTime('submission_end_at')->nullable()->after('submission_start_at');
            $table->json('timeline')->nullable()->after('submission_end_at');
            $table->string('whatsapp_number', 30)->nullable()->after('timeline');
            $table->string('whatsapp_group_url', 500)->nullable()->after('whatsapp_number');
        });

        DB::table('competition_sessions')->orderBy('id')->get()->each(function ($session) {
            $competition = DB::table('competitions')->where('id', $session->competition_id)->first();
            if (! $competition) return;
            DB::table('competition_sessions')->where('id', $session->id)->update([
                'fee' => $competition->fee,
                'team_update_deadline_at' => $competition->team_update_deadline_at,
                'submission_start_at' => $competition->submission_start_at,
                'submission_end_at' => $competition->submission_end_at,
                'timeline' => $competition->timeline,
                'whatsapp_group_url' => $competition->whatsapp_group_url,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('competition_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'fee', 'team_update_deadline_at', 'submission_start_at', 'submission_end_at',
                'timeline', 'whatsapp_number', 'whatsapp_group_url',
            ]);
        });

        Schema::table('competition_venues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pic_user_id');
            $table->dropColumn(['slug', 'activity_start_date', 'activity_end_date', 'field_photo_url']);
        });
    }
};
