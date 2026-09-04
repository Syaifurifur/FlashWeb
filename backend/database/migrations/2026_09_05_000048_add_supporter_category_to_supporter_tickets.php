<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supporter_tickets', function (Blueprint $table) {
            $table->string('grade', 20)->change();
            $table->string('supporter_category', 20)->nullable()->after('grade');
        });
    }

    public function down(): void
    {
        DB::table('supporter_tickets')->where('grade', 'other')->update(['grade' => 'XII']);

        Schema::table('supporter_tickets', function (Blueprint $table) {
            $table->dropColumn('supporter_category');
            $table->enum('grade', ['X', 'XI', 'XII'])->change();
        });
    }
};
