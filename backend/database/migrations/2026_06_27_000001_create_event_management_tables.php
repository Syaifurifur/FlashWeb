<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'pic'])->default('pic')->after('password');
            $table->string('api_token', 64)->nullable()->unique()->after('role');
        });

        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->enum('category', ['Akademik', 'Olahraga', 'Seni']);
            $table->text('short_description');
            $table->longText('description');
            $table->unsignedInteger('quota');
            $table->decimal('fee', 12, 2)->default(0);
            $table->date('registration_start');
            $table->date('registration_end');
            $table->date('event_date');
            $table->string('location')->default('Online');
            $table->string('poster_url')->nullable();
            $table->json('requirements')->nullable();
            $table->json('timeline')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('competition_id')->nullable()->after('api_token')->constrained()->nullOnDelete();
        });

        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_code')->unique();
            $table->string('full_name');
            $table->string('whatsapp', 20);
            $table->string('email');
            $table->string('birth_place');
            $table->date('birth_date');
            $table->enum('grade', ['X', 'XI', 'XII']);
            $table->string('nisn', 10);
            $table->text('mother_name');
            $table->string('school_name');
            $table->string('teacher_name');
            $table->string('teacher_contact', 20);
            $table->string('school_code')->nullable();
            $table->string('team_name')->nullable();
            $table->string('participant_category')->nullable();
            $table->string('student_card_path');
            $table->string('delegation_letter_path');
            $table->string('photo_path');
            $table->string('payment_proof_path')->nullable();
            $table->boolean('consent')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected', 'revision'])->default('pending');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['competition_id', 'nisn']);
            $table->index(['competition_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('competition_id'));
        Schema::dropIfExists('competitions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'api_token']);
        });
    }
};
