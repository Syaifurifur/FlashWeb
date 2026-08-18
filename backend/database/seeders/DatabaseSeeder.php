<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitionType;
use App\Models\EventEdition;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $admin = User::updateOrCreate(['email' => 'admin@bsiflash2027.id'], ['name' => 'Super Admin', 'password' => 'password123', 'role' => 'super_admin']);
        $edition = EventEdition::resolveCurrent();

        $items = [
            ['title'=>'Olimpiade Sains Nusantara','category'=>'Akademik','short_description'=>'Panggung pelajar paling tajam untuk menaklukkan tantangan sains lintas disiplin.','description'=>'Uji kemampuan Matematika, Fisika, Biologi, dan Kimia dalam kompetisi nasional yang dirancang untuk calon inovator muda. Babak penyisihan dilaksanakan daring dan final berlangsung secara langsung di Jakarta.','quota'=>320,'fee'=>75000,'registration_start'=>'2026-06-01','registration_end'=>'2026-08-12','event_date'=>'2026-08-30','location'=>'Jakarta & Online','poster_url'=>'/gallery/2023/talent-3-1.png','is_featured'=>true],
            ['title'=>'National Basketball Cup','category'=>'Olahraga','participation_type'=>'team','team_size'=>5,'short_description'=>'Bawa nama sekolahmu ke arena basket antarpelajar paling bergengsi.','description'=>'Turnamen basket 5v5 tingkat SMA dengan sistem grup dan knockout. Setiap sekolah dapat mengirim maksimal dua tim.','quota'=>64,'fee'=>350000,'registration_start'=>'2026-06-15','registration_end'=>'2026-08-24','event_date'=>'2026-09-12','location'=>'GOR Soemantri, Jakarta','poster_url'=>'/gallery/2024/galeri-2024-bsi-flash-7.jpg','is_featured'=>true],
            ['title'=>'Lensa Muda Film Festival','category'=>'Seni','short_description'=>'Ceritakan Indonesia lewat film pendek orisinal buatan pelajar.','description'=>'Festival film pendek pelajar berdurasi 5–12 menit. Karya terbaik diputar pada malam apresiasi dan dinilai sineas nasional.','quota'=>120,'fee'=>50000,'registration_start'=>'2026-07-01','registration_end'=>'2026-09-05','event_date'=>'2026-09-28','location'=>'Bandung Creative Hub','poster_url'=>'/gallery/2025/Juara-1-BSI-Star.jpeg','is_featured'=>false],
            ['title'=>'Debat Bahasa Indonesia','category'=>'Akademik','participation_type'=>'team','team_size'=>3,'short_description'=>'Adu gagasan, data, dan keberanian di panggung debat pelajar nasional.','description'=>'Kompetisi debat Bahasa Indonesia format Asian Parliamentary untuk tim beranggotakan tiga siswa.','quota'=>96,'fee'=>150000,'registration_start'=>'2026-06-20','registration_end'=>'2026-08-18','event_date'=>'2026-09-06','location'=>'Online','poster_url'=>'/gallery/2023/talent-7-1.png','is_featured'=>false],
        ];
        foreach($items as $index=>$item){
            $item['category']=match($item['category']){'Olahraga'=>'Sport Competition','Seni'=>'Talent Competition',default=>'Science Competition'};
            $item['slug']=Str::slug($item['title']);
            $item['event_edition_id']=$edition->id;
            $item['competition_type_id']=CompetitionType::where('category_group',$item['category'])->value('id');
            $item['requirements']=[];
            $item['guides']=[
                ['title'=>'Ketentuan Pendaftaran','content'=>"Pendaftaran dilakukan secara online.\nPastikan seluruh data peserta diisi dengan benar."],
                ['title'=>'Ketentuan Peserta','content'=>"Siswa aktif SMA/sederajat kelas X–XII.\nSetiap peserta hanya boleh mendaftar satu kali."],
                ['title'=>'Ketentuan Berkas','content'=>"Siapkan kartu pelajar yang masih berlaku.\nUnggah surat delegasi resmi dari sekolah."],
            ];
            $item['timeline']=[['date'=>$item['registration_start'],'label'=>'Pendaftaran dibuka'],['date'=>$item['registration_end'],'label'=>'Pendaftaran ditutup'],['date'=>$item['event_date'],'label'=>'Hari kompetisi']];
            $competition=Competition::updateOrCreate(['slug'=>$item['slug']],$item);
            if($index===0) User::updateOrCreate(['email'=>'pic@bsiflash2027.id'],['name'=>'PIC Olimpiade Sains','whatsapp'=>'081234567890','password'=>'password123','role'=>'pic','competition_id'=>$competition->id]);
        }
    }
}
