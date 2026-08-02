<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dateTime('team_update_deadline_at')->nullable()->after('registration_end');
            $table->dateTime('document_upload_deadline_at')->nullable()->after('team_update_deadline_at');
        });

        DB::table('competitions')->select('id', 'registration_end')->orderBy('id')->each(function ($competition) {
            $deadline = $competition->registration_end.' 16:59:59';
            DB::table('competitions')->where('id', $competition->id)->update([
                'team_update_deadline_at' => $deadline,
                'document_upload_deadline_at' => $deadline,
            ]);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->string('school_logo_path')->nullable()->after('school_address');
            $table->string('statement_letter_path')->nullable()->after('delegation_letter_path');
            $table->timestamp('team_completed_at')->nullable()->after('consent');
            $table->timestamp('documents_completed_at')->nullable()->after('team_completed_at');

            $table->string('birth_place')->nullable()->change();
            $table->date('birth_date')->nullable()->change();
            $table->enum('grade', ['X', 'XI', 'XII'])->nullable()->change();
            $table->string('nisn', 10)->nullable()->change();
            $table->text('mother_name')->nullable()->change();
            $table->string('school_name')->nullable()->change();
            $table->string('teacher_name')->nullable()->change();
            $table->string('teacher_contact', 20)->nullable()->change();
            $table->string('student_card_path')->nullable()->change();
            $table->string('delegation_letter_path')->nullable()->change();
            $table->string('photo_path')->nullable()->change();
        });

        Schema::table('registration_members', function (Blueprint $table) {
            $table->string('nisn', 10)->nullable()->change();
            $table->string('birth_place')->nullable()->change();
            $table->date('birth_date')->nullable()->change();
            $table->enum('grade', ['X', 'XI', 'XII'])->nullable()->change();
            $table->text('mother_name')->nullable()->change();
            $table->string('student_card_path')->nullable()->change();
            $table->string('photo_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['school_logo_path', 'statement_letter_path', 'team_completed_at', 'documents_completed_at']);
        });
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['team_update_deadline_at', 'document_upload_deadline_at']);
        });
    }
};
