<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('registration_members', function (Blueprint $table) {
            $table->string('email', 150)->nullable()->after('full_name');
            $table->string('whatsapp', 20)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('registration_members', fn (Blueprint $table) => $table->dropColumn(['email', 'whatsapp']));
    }
};
