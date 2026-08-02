<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('city', 120);
            $table->string('venue', 180);
            $table->date('activity_start_date');
            $table->date('activity_end_date');
            $table->date('competition_start_date');
            $table->date('competition_end_date');
            $table->string('information_label', 120)->nullable();
            $table->dateTime('information_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['competition_id', 'is_active', 'sort_order']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('competition_session_id')->nullable()->after('competition_id')
                ->constrained('competition_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('competition_session_id');
        });
        Schema::dropIfExists('competition_sessions');
    }
};
