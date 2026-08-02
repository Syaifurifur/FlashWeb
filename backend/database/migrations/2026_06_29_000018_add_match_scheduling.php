<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->json('schedule_venues')->nullable()->after('timeline');
        });

        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->default(60)->after('scheduled_at');
        });

        DB::table('tournament_matches')->whereNull('scheduled_at')->where('status', 'scheduled')->update(['status' => 'unscheduled']);
        DB::table('tournament_matches')->whereNotNull('scheduled_at')->where('status', 'scheduled')->update(['status' => 'upcoming']);

        Schema::create('tournament_schedule_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_draw_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->string('venue', 160);
            $table->dateTime('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_schedule_blocks');
        Schema::table('tournament_matches', fn (Blueprint $table) => $table->dropColumn('duration_minutes'));
        Schema::table('competitions', fn (Blueprint $table) => $table->dropColumn('schedule_venues'));
    }
};
