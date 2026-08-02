<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('registration_members', function (Blueprint $table) {
            $table->timestamp('nisn_verified_at')->nullable();
            $table->foreignId('nisn_verified_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registration_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nisn_verified_by');
            $table->dropColumn('nisn_verified_at');
        });
    }
};
