<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitionSession;
use App\Models\CompetitionType;
use App\Models\CompetitionVenue;
use App\Models\EventEdition;
use App\Models\Registration;
use App\Models\RegistrationMember;
use App\Models\RegistrationOfficial;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BadmintonDemoSeeder extends Seeder
{
    public function run(): void
    {
        $edition = EventEdition::resolveCurrent();
        $admin = User::where('role', 'super_admin')->first();
        if (! $admin) {
            throw new RuntimeException('Akun super admin diperlukan untuk membuat dummy bulutangkis.');
        }

        $pic = User::where('role', 'pic')->orderBy('id')->first();
        $spv = User::where('role', 'spv')->orderBy('id')->first();
        $venue = CompetitionVenue::where('event_edition_id', $edition->id)
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN slug = 'bogor' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        $sportTypeId = CompetitionType::where('category_group', 'Sport Competition')->value('id');
        $city = $venue?->city ?? 'Bogor';
        $venueName = $venue?->name ?? 'Sport Center UBSI Kampus Bogor A';
        $courts = [
            "Lapangan 1 - {$venueName}",
            "Lapangan 2 - {$venueName}",
            "Lapangan 3 - {$venueName}",
            "Lapangan 4 - {$venueName}",
        ];

        $regStart = now()->subWeeks(2)->toDateString();
        $regEnd = now()->addWeeks(2)->toDateString();
        $eventStart = now()->addWeeks(3)->toDateString();
        $eventEnd = now()->addWeeks(3)->addDays(2)->toDateString();
        $tmDate = now()->addWeeks(2)->addDays(4)->toDateString();

        $timeline = [
            ['label' => 'Technical Meeting', 'type' => 'single', 'date' => $tmDate],
            [
                'label' => 'Jadwal Lomba',
                'type' => 'range',
                'start_date' => $eventStart,
                'end_date' => $eventEnd,
                'date' => "{$eventStart}|{$eventEnd}",
            ],
        ];

        $teams = [
            ['SMAN 1 Bogor A', 'SMAN 1 Bogor', 'Rizky Pratama', 'Dimas Saputra'],
            ['SMAN 1 Bogor B', 'SMAN 1 Bogor', 'Fajar Nugroho', 'Bagas Ramadhan'],
            ['SMAN 2 Bogor A', 'SMAN 2 Bogor', 'Aditya Mahendra', 'Kevin Wijaya'],
            ['SMAN 3 Bogor', 'SMAN 3 Bogor', 'Reza Pahlevi', 'Ilham Kurniawan'],
            ['SMAN 4 Bogor', 'SMAN 4 Bogor', 'Arif Hidayat', 'Yoga Permana'],
            ['SMAN 5 Bogor', 'SMAN 5 Bogor', 'Rendi Firmansyah', 'Taufik Akbar'],
            ['SMAN 6 Bogor', 'SMAN 6 Bogor', 'Naufal Hakim', 'Farhan Alfarizi'],
            ['SMAN 7 Bogor', 'SMAN 7 Bogor', 'Dafa Maulana', 'Galang Prasetyo'],
            ['SMAN 8 Bogor', 'SMAN 8 Bogor', 'Bayu Setiawan', 'Hendra Kusuma'],
            ['SMAN 9 Bogor', 'SMAN 9 Bogor', 'Iqbal Zulkarnain', 'Raka Putra'],
            ['SMAN 10 Bogor', 'SMAN 10 Bogor', 'Alvin Santoso', 'Bima Aryanto'],
            ['SMKN 1 Bogor A', 'SMKN 1 Bogor', 'Fikri Haikal', 'Surya Darmawan'],
            ['SMKN 1 Bogor B', 'SMKN 1 Bogor', 'Gilang Anggara', 'Yusuf Maulana'],
            ['SMKN 2 Bogor', 'SMKN 2 Bogor', 'Teguh Santosa', 'Wahyu Utomo'],
            ['SMKN 3 Bogor', 'SMKN 3 Bogor', 'Candra Gunawan', 'Eko Wibowo'],
            ['SMKN 4 Bogor', 'SMKN 4 Bogor', 'Rian Febrianto', 'Dika Hermawan'],
            ['SMA Regina Pacis', 'SMA Regina Pacis Bogor', 'Jonathan Christian', 'Michael Tanoto'],
            ['SMA Budi Mulia', 'SMA Budi Mulia Bogor', 'Daniel Hartono', 'Samuel Halim'],
            ['SMA Kesatuan', 'SMA Kesatuan Bogor', 'Andrew Susanto', 'Nicholas Salim'],
            ['SMA Bina Insani', 'SMA Bina Insani Bogor', 'Zaki Mubarok', 'Rafli Ananda'],
            ['SMA Kornita IPB', 'SMA Kornita IPB Bogor', 'Haikal Firdaus', 'Azka Rasyid'],
            ['SMA Kosgoro Bogor', 'SMA Kosgoro Bogor', 'Deni Kurnia', 'Robby Cahyadi'],
            ['SMA Rimba Madya', 'SMA Rimba Madya Bogor', 'Panji Laksono', 'Satria Pamungkas'],
            ['SMA YPHB Bogor', 'SMA YPHB Bogor', 'Aldi Ramdani', 'Rafi Syahputra'],
            ['MAN 1 Bogor', 'MAN 1 Bogor', 'Fadlan Azhari', 'Luthfi Rahman'],
            ['MAN 2 Bogor', 'MAN 2 Bogor', 'Salman Alfarisi', 'Syahrul Gunawan'],
            ['SMAN 1 Cibinong', 'SMAN 1 Cibinong', 'Bintang Samudra', 'Pratama Putra'],
            ['SMAN 2 Cibinong', 'SMAN 2 Cibinong', 'Haris Munandar', 'Rizki Fauzan'],
            ['SMAN 1 Ciawi', 'SMAN 1 Ciawi', 'Guntur Mahardika', 'Krisna Aditama'],
            ['SMAN 1 Dramaga', 'SMAN 1 Dramaga', 'Yoga Adriansyah', 'Ferdyansyah'],
            ['SMAN 1 Parung', 'SMAN 1 Parung', 'Aldo Setyawan', 'Rama Yudistira'],
            ['SMAN 1 Citeureup', 'SMAN 1 Citeureup', 'Hasanudin', 'Miftahul Huda'],
            ['SMAN 1 Depok', 'SMAN 1 Depok', 'Arya Bima', 'Dito Nugraha'],
            ['SMAN 2 Depok', 'SMAN 2 Depok', 'Kresna Wibisana', 'Vito Pradipta'],
            ['SMAN 3 Depok', 'SMAN 3 Depok', 'Akmal Ramadhan', 'Farel Aditya'],
            ['SMKN 1 Depok', 'SMKN 1 Depok', 'Tio Herlambang', 'Zidan Pratama'],
            ['SMAIT Nurul Fikri', 'SMAIT Nurul Fikri Depok', 'Ammar Yasir', 'Zaidan Alamsyah'],
            ['SMA Al Azhar BSD', 'SMA Islam Al Azhar BSD', 'Raffi Ahmadinejad', 'Keanu Malik'],
            ['SMA Labschool Cibubur', 'SMA Labschool Cibubur', 'Kenji Mahardika', 'Raka Danendra'],
            ['SMA Global Mandiri', 'SMA Global Mandiri Cibubur', 'Darren Wijaya', 'Sean Alexander'],
        ];

        DB::transaction(function () use (
            $admin,
            $pic,
            $spv,
            $venue,
            $edition,
            $sportTypeId,
            $city,
            $venueName,
            $courts,
            $regStart,
            $regEnd,
            $eventStart,
            $eventEnd,
            $timeline,
            $teams
        ) {
            $competition = Competition::updateOrCreate(
                ['slug' => 'bulutangkis-ganda-putra'],
                [
                    'event_edition_id' => $edition->id,
                    'competition_type_id' => $sportTypeId,
                    'title' => 'BULUTANGKIS GANDA PUTRA',
                    'category' => 'Sport Competition',
                    'participation_type' => 'team',
                    'team_size' => 2,
                    'official_count' => 1,
                    'pic_slots' => 1,
                    'short_description' => 'Turnamen Bulutangkis Ganda Putra antarsekolah SMA/SMK/MA.',
                    'description' => 'Kompetisi bulutangkis kategori Ganda Putra tingkat SMA/SMK/MA sederajat. Setiap tim terdiri atas dua pemain ganda putra dan satu official/pelatih.',
                    'quota' => 40,
                    'fee' => 150000,
                    'bank_name' => 'Bank Mandiri',
                    'bank_account_number' => '123 000 464 7683',
                    'bank_account_holder' => 'Bina Sarana Informatika',
                    'payment_note' => 'Cantumkan nama peserta atau tim pada berita transfer.',
                    'registration_start' => $regStart,
                    'registration_end' => $regEnd,
                    'team_update_deadline_at' => now()->addWeeks(2)->endOfDay(),
                    'document_upload_deadline_at' => now()->addWeeks(2)->endOfDay(),
                    'event_date' => $eventStart,
                    'location' => $city,
                    'poster_url' => '/gallery/2024/galeri-2024-bsi-flash-7.jpg',
                    'requirements' => [],
                    'guides' => [
                        [
                            'title' => 'Ketentuan Umum Ganda Putra',
                            'content' => "Peserta merupakan siswa aktif SMA/SMK/MA sederajat.\nSatu pasangan ganda putra terdiri dari 2 pemain dari sekolah yang sama.\nPertandingan menggunakan sistem rally point 3x21 sesuai aturan PBSI/BWF.",
                        ],
                    ],
                    'timeline' => $timeline,
                    'schedule_venues' => $courts,
                    'is_featured' => false,
                ]
            );

            $session = CompetitionSession::updateOrCreate(
                [
                    'competition_id' => $competition->id,
                    'city' => $city,
                ],
                [
                    'venue_id' => $venue?->id,
                    'pic_user_id' => $pic?->id,
                    'supervisor_user_id' => $spv?->id,
                    'pic_slots' => 1,
                    'supervisor_slots' => 1,
                    'venue' => $venueName,
                    'activity_start_date' => $venue?->activity_start_date ?? $eventStart,
                    'activity_end_date' => $venue?->activity_end_date ?? $eventEnd,
                    'competition_start_date' => $eventStart,
                    'competition_end_date' => $eventEnd,
                    'quota' => 40,
                    'fee' => 150000,
                    'team_update_deadline_at' => now()->addWeeks(2)->endOfDay(),
                    'timeline' => $timeline,
                    'schedule_venues' => $courts,
                    'whatsapp_number' => $pic?->whatsapp ?: '081234567890',
                    'sort_order' => 0,
                    'is_active' => true,
                ]
            );

            $staffRows = [];
            if ($pic) {
                $staffRows[$pic->id] = ['role' => 'pic', 'sort_order' => 0];
            }
            if ($spv) {
                $staffRows[$spv->id] = ['role' => 'spv', 'sort_order' => 0];
            }
            if (! empty($staffRows)) {
                $session->staff()->sync($staffRows);
            }

            foreach ($teams as $index => [$teamName, $schoolName, $player1, $player2]) {
                $number = $index + 1;
                $padded = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                $ticket = "DEMO-BDM-{$padded}";

                $registration = Registration::updateOrCreate(
                    ['ticket_code' => $ticket],
                    [
                        'event_edition_id' => $edition->id,
                        'competition_id' => $competition->id,
                        'competition_session_id' => $session->id,
                        'full_name' => $player1,
                        'team_name' => $teamName,
                        'whatsapp' => '081410'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                        'email' => "badminton{$padded}@demo.test",
                        'birth_place' => $city,
                        'birth_date' => '2009-05-'.str_pad((string) (($number % 28) + 1), 2, '0', STR_PAD_LEFT),
                        'grade' => $number % 2 === 0 ? 'XI' : 'XII',
                        'nisn' => (string) (9500000000 + $number * 10 + 1),
                        'mother_name' => "Ibu {$player1}",
                        'school_name' => $schoolName,
                        'school_city' => $city,
                        'school_address' => "Jl. Pendidikan No. {$number}, {$city}",
                        'teacher_name' => "Pembina {$schoolName}",
                        'teacher_contact' => '081420'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                        'consent' => true,
                        'status' => 'approved',
                        'team_completed_at' => now(),
                        'documents_completed_at' => now(),
                        'payment_verified_at' => now(),
                        'payment_verified_by' => $admin->id,
                        'reviewed_by' => $admin->id,
                        'reviewed_at' => now(),
                        'review_note' => 'Data dummy 40 tim Bulutangkis Ganda Putra siap untuk simulasi drawing.',
                    ]
                );

                $registration->members()->where('member_order', '>', 2)->delete();
                foreach ([1 => $player1, 2 => $player2] as $memberOrder => $playerName) {
                    RegistrationMember::updateOrCreate(
                        ['registration_id' => $registration->id, 'member_order' => $memberOrder],
                        [
                            'competition_id' => $competition->id,
                            'full_name' => $playerName,
                            'email' => "bdm{$padded}.p{$memberOrder}@demo.test",
                            'whatsapp' => '08143'.str_pad((string) ($number * 10 + $memberOrder), 7, '0', STR_PAD_LEFT),
                            'nisn' => (string) (9500000000 + $number * 10 + $memberOrder),
                            'birth_place' => $city,
                            'birth_date' => '2009-06-'.str_pad((string) (($number % 28) + 1), 2, '0', STR_PAD_LEFT),
                            'grade' => $memberOrder === 1 ? 'XI' : 'XII',
                            'mother_name' => "Ibu {$playerName}",
                            'nisn_verified_at' => now(),
                            'nisn_verified_by' => $admin->id,
                        ]
                    );
                }

                $registration->officials()->where('official_order', '>', 1)->delete();
                RegistrationOfficial::updateOrCreate(
                    ['registration_id' => $registration->id, 'official_order' => 1],
                    [
                        'full_name' => "Coach {$teamName}",
                        'position' => 'Pelatih',
                        'whatsapp' => '08144'.str_pad((string) $number, 7, '0', STR_PAD_LEFT),
                    ]
                );
            }
        });

        $this->command?->info('40 tim dummy BULUTANGKIS GANDA PUTRA siap di '.$city.' untuk uji coba drawing.');
    }
}

