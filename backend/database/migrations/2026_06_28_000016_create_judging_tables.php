<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->longText('judging_guide')->nullable()->after('guides');
            $table->timestamp('judging_locked_at')->nullable()->after('submission_end_at');
            $table->timestamp('results_announced_at')->nullable()->after('judging_locked_at');
        });
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('work_verification_status', 20)->default('pending')->after('work_submitted_at');
            $table->text('work_verification_note')->nullable()->after('work_verification_status');
            $table->foreignId('work_verified_by')->nullable()->after('work_verification_note')->constrained('users')->nullOnDelete();
            $table->timestamp('work_verified_at')->nullable()->after('work_verified_by');
        });
        Schema::create('judging_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->decimal('max_score', 8, 2)->default(100);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });
        Schema::create('judge_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('judge_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['registration_id', 'judge_id']);
        });
        Schema::create('judge_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('judge_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('judging_criterion_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 8, 2);
            $table->timestamps();
            $table->unique(['judge_assignment_id', 'judging_criterion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judge_scores');
        Schema::dropIfExists('judge_assignments');
        Schema::dropIfExists('judging_criteria');
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_verified_by');
            $table->dropColumn(['work_verification_status','work_verification_note','work_verified_at']);
        });
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['judging_guide','judging_locked_at','results_announced_at']);
        });
    }
};
