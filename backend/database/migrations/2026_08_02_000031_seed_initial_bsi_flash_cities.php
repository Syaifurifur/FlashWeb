<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $cities = [
        ['slug'=>'bogor','city'=>'Bogor','name'=>'BSI Sport Center UBSI Kampus Bogor A','start'=>'2026-09-03','end'=>'2026-09-09'],
        ['slug'=>'pontianak','city'=>'Pontianak','name'=>'BSI Sport Center UBSI Kampus Pontianak','start'=>'2026-09-30','end'=>'2026-10-06'],
        ['slug'=>'jakarta','city'=>'Jakarta','name'=>'BSI Sport Center UBSI Kampus Cengkareng','start'=>'2026-10-15','end'=>'2026-10-20'],
        ['slug'=>'tegal','city'=>'Tegal','name'=>'BSI Sport Center UBSI Kampus Tegal','start'=>'2026-11-12','end'=>'2026-11-17'],
        ['slug'=>'tangerang-raya','city'=>'Tangerang Raya','name'=>'BSI Sport Center UBSI Kampus BSD','start'=>'2026-12-10','end'=>'2026-12-16'],
        ['slug'=>'bekasi','city'=>'Bekasi','name'=>'BSI Sport Center UBSI Bekasi','start'=>'2027-01-13','end'=>'2027-01-19'],
        ['slug'=>'kaliabang','city'=>'Kaliabang','name'=>'BSI Convention Center','start'=>'2027-02-06','end'=>'2027-02-06'],
    ];

    public function up(): void
    {
        if (app()->environment('testing') || DB::table('competition_venues')->exists()) return;

        $picId = DB::table('users')->where('role', 'pic')->value('id');
        $photo = 'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=1600&q=85';
        foreach ($this->cities as $city) {
            DB::table('competition_venues')->insert([
                'slug'=>$city['slug'], 'city'=>$city['city'], 'name'=>$city['name'],
                'address'=>$city['name'].', '.$city['city'],
                'activity_start_date'=>$city['start'], 'activity_end_date'=>$city['end'],
                'field_photo_url'=>$photo, 'pic_user_id'=>$picId, 'is_active'=>true,
                'created_at'=>now(), 'updated_at'=>now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('competition_venues')
            ->whereIn('slug', array_column($this->cities, 'slug'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('competition_sessions')->whereColumn('competition_sessions.venue_id', 'competition_venues.id'))
            ->delete();
    }
};
