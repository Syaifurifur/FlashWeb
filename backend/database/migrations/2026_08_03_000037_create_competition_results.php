<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->string('title', 120);
            $table->enum('source', ['judging', 'tournament', 'manual']);
            $table->decimal('score', 12, 2)->nullable();
            $table->timestamp('announced_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['competition_id', 'competition_session_id', 'rank', 'source'], 'competition_results_rank_unique');
            $table->index(['competition_id', 'source', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_results');
    }
};
