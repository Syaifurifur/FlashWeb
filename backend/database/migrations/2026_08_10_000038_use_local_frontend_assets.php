<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('competitions')->where('title', 'Olimpiade Sains Nusantara')->update(['poster_url' => '/gallery/2023/talent-3-1.png']);
        DB::table('competitions')->where('title', 'National Basketball Cup')->update(['poster_url' => '/gallery/2024/galeri-2024-bsi-flash-7.jpg']);
        DB::table('competitions')->where('title', 'Lensa Muda Film Festival')->update(['poster_url' => '/gallery/2025/Juara-1-BSI-Star.jpeg']);
        DB::table('competitions')->where('title', 'Debat Bahasa Indonesia')->update(['poster_url' => '/gallery/2023/talent-7-1.png']);

        DB::table('competition_venues')->whereNotNull('field_photo_url')->update(['field_photo_url' => '/gallery/2024/galeri-2024-bsi-flash-7.jpg']);
    }

    public function down(): void
    {
        // Local asset paths are intentionally retained on rollback.
    }
};
