<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->enum('participation_type', ['individual', 'team'])->default('individual')->after('category');
            $table->unsignedTinyInteger('team_size')->default(1)->after('participation_type');
        });

        Schema::create('registration_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('member_order');
            $table->string('full_name');
            $table->string('nisn', 10);
            $table->string('birth_place');
            $table->date('birth_date');
            $table->enum('grade', ['X', 'XI', 'XII']);
            $table->text('mother_name');
            $table->string('student_card_path');
            $table->string('photo_path');
            $table->timestamps();
            $table->unique(['competition_id', 'nisn']);
            $table->unique(['registration_id', 'member_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_members');
        Schema::table('competitions', fn (Blueprint $table) => $table->dropColumn(['participation_type', 'team_size']));
    }
};
