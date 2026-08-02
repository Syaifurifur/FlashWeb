<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('competitions', fn (Blueprint $table) => $table->unsignedTinyInteger('pic_slots')->default(1)->after('team_size'));
        Schema::table('users', fn (Blueprint $table) => $table->string('whatsapp',20)->nullable()->after('email'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('whatsapp'));
        Schema::table('competitions', fn (Blueprint $table) => $table->dropColumn('pic_slots'));
    }
};
