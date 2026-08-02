<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_venues', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('city', 120);
            $table->text('address');
            $table->string('maps_url', 1000)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'city']);
        });

        Schema::table('competition_sessions', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->after('competition_id')
                ->constrained('competition_venues')->nullOnDelete();
        });

        $existingVenues = DB::table('competition_sessions')
            ->select('city', 'venue')->distinct()->get();

        foreach ($existingVenues as $existing) {
            $venueId = DB::table('competition_venues')->insertGetId([
                'name' => $existing->venue,
                'city' => $existing->city,
                'address' => $existing->venue.', '.$existing->city,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('competition_sessions')
                ->where('city', $existing->city)
                ->where('venue', $existing->venue)
                ->update(['venue_id' => $venueId]);
        }
    }

    public function down(): void
    {
        Schema::table('competition_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
        });

        Schema::dropIfExists('competition_venues');
    }
};
