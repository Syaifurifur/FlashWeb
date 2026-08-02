<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dateTime('submission_start_at')->nullable()->after('event_date');
            $table->dateTime('submission_end_at')->nullable()->after('submission_start_at');
        });
        Schema::table('registrations', function (Blueprint $table) {
            $table->text('work_submission_url')->nullable()->after('payment_proof_path');
            $table->timestamp('work_submitted_at')->nullable()->after('work_submission_url');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['work_submission_url', 'work_submitted_at']);
        });
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['submission_start_at', 'submission_end_at']);
        });
    }
};
