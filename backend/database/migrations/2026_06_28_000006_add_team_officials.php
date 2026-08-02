<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->unsignedTinyInteger('official_count')->default(0)->after('team_size');
        });

        Schema::create('registration_officials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('official_order');
            $table->string('full_name', 120);
            $table->string('position', 80);
            $table->string('whatsapp', 20);
            $table->timestamps();
            $table->unique(['registration_id', 'official_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_officials');
        Schema::table('competitions', fn (Blueprint $table) => $table->dropColumn('official_count'));
    }
};
