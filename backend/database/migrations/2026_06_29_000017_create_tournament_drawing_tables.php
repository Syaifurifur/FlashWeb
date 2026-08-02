<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tournament_draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('mode', 20);
            $table->string('format', 40);
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('drawn_at');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['competition_id','version']);
        });
        Schema::create('tournament_draw_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_draw_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('slot_number');
            $table->unsignedInteger('seed_number')->nullable();
            $table->string('group_name', 20)->nullable();
            $table->boolean('is_bye')->default(false);
            $table->timestamps();
            $table->unique(['tournament_draw_id','slot_number']);
        });
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_draw_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 30)->default('main');
            $table->unsignedInteger('round_number')->default(1);
            $table->string('round_label', 80);
            $table->unsignedInteger('match_number');
            $table->string('group_name', 20)->nullable();
            $table->foreignId('participant_a_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->foreignId('participant_b_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->foreignId('source_a_match_id')->nullable()->constrained('tournament_matches')->nullOnDelete();
            $table->string('source_a_outcome', 10)->nullable();
            $table->foreignId('source_b_match_id')->nullable()->constrained('tournament_matches')->nullOnDelete();
            $table->string('source_b_outcome', 10)->nullable();
            $table->decimal('score_a', 8, 2)->nullable();
            $table->decimal('score_b', 8, 2)->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('venue', 160)->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();
            $table->unique(['tournament_draw_id','match_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_matches');
        Schema::dropIfExists('tournament_draw_entries');
        Schema::dropIfExists('tournament_draws');
    }
};
