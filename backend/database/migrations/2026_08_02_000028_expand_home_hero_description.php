<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $description = 'BSI Flash 2027 merupakan rangkaian kompetisi pelajar tingkat nasional yang diselenggarakan secara hybrid, baik daring maupun luring, untuk siswa SLTA dan sederajat dari berbagai daerah di Indonesia. Program ini menjadi ruang bagi generasi muda untuk mengembangkan kreativitas, sportivitas, kemampuan akademik, keterampilan digital, kepemimpinan, kerja sama tim, serta keberanian dalam menunjukkan potensi terbaiknya. Melalui kategori Sport Competition, Talent Competition, dan Science Competition, setiap peserta memperoleh pengalaman berkompetisi yang terarah, bertemu dengan pelajar berbakat lainnya, mendapatkan informasi kegiatan yang transparan, serta memantau seluruh proses pendaftaran melalui satu dashboard. BSI Flash tidak hanya menghadirkan perlombaan, tetapi juga membangun ekosistem pembelajaran yang mendorong lahirnya generasi mandiri, berprestasi, adaptif, dan siap memberikan kontribusi positif untuk masa depan Indonesia yang lebih baik.';

    public function up(): void
    {
        $content = DB::table('site_contents')->where('key', 'home_hero')->value('content');
        if (! $content) return;
        $data = json_decode($content, true) ?: [];
        $data['description'] = $this->description;
        DB::table('site_contents')->where('key', 'home_hero')->update(['content'=>json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at'=>now()]);
    }

    public function down(): void
    {
        $content = DB::table('site_contents')->where('key', 'home_hero')->value('content');
        if (! $content) return;
        $data = json_decode($content, true) ?: [];
        if (($data['description'] ?? null) === $this->description) {
            $data['description'] = 'Perlombaan secara hybrid (online dan offline) berskala nasional antar siswa SLTA/sederajat dalam mendukung kreativitas dan sportivitas untuk berkontribusi mencetak generasi mandiri yang bertalenta digital untuk Indonesia yang lebih baik.';
            DB::table('site_contents')->where('key', 'home_hero')->update(['content'=>json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at'=>now()]);
        }
    }
};
