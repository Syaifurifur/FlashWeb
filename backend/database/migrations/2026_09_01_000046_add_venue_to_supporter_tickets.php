<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supporter_tickets', function (Blueprint $table) {
            $table->foreignId('competition_venue_id')->nullable()->after('event_edition_id')
                ->constrained('competition_venues')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supporter_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('competition_venue_id');
        });
    }
};
