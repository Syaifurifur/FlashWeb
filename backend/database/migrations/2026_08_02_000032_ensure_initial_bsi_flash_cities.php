<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing') || DB::table('competition_venues')->exists()) return;

        $picId = DB::table('users')->where('role', 'pic')->value('id');
        $photo = 'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=1600&q=85';
        $cities = [
            ['bogor','Bogor','BSI Sport Center UBSI Kampus Bogor A','2026-09-03','2026-09-09'],
            ['pontianak','Pontianak','BSI Sport Center UBSI Kampus Pontianak','2026-09-30','2026-10-06'],
            ['jakarta','Jakarta','BSI Sport Center UBSI Kampus Cengkareng','2026-10-15','2026-10-20'],
            ['tegal','Tegal','BSI Sport Center UBSI Kampus Tegal','2026-11-12','2026-11-17'],
            ['tangerang-raya','Tangerang Raya','BSI Sport Center UBSI Kampus BSD','2026-12-10','2026-12-16'],
            ['bekasi','Bekasi','BSI Sport Center UBSI Bekasi','2027-01-13','2027-01-19'],
            ['kaliabang','Kaliabang','BSI Convention Center','2027-02-06','2027-02-06'],
        ];
        foreach ($cities as [$slug,$city,$name,$start,$end]) {
            DB::table('competition_venues')->insert([
                'slug'=>$slug,'city'=>$city,'name'=>$name,'address'=>$name.', '.$city,
                'activity_start_date'=>$start,'activity_end_date'=>$end,'field_photo_url'=>$photo,
                'pic_user_id'=>$picId,'is_active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }

    public function down(): void {}
};
