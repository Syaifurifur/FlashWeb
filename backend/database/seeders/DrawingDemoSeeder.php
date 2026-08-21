<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitionType;
use App\Models\EventEdition;
use App\Models\Registration;
use App\Models\RegistrationMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class DrawingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $edition = EventEdition::resolveCurrent();
        $admin = User::where('role', 'super_admin')->first()
            ?? User::create([
                'name' => 'Super Admin',
                'email' => 'admin@bsiflash2027.id',
                'password' => 'password123',
                'role' => 'super_admin',
            ]);

        $competition = Competition::updateOrCreate(
            ['slug' => 'demo-drawing-force-majeure'],
            [
                'event_edition_id' => $edition->id,
                'competition_type_id' => CompetitionType::where('category_group', 'Sport Competition')->value('id'),
                'title' => '[DEMO] Drawing Force Majeure',
                'category' => 'Sport Competition',
                'participation_type' => 'team',
                'team_size' => 5,
                'official_count' => 2,
                'pic_slots' => 1,
                'short_description' => 'Data simulasi untuk mencoba drawing dan keputusan force majeure.',
                'description' => 'Kompetisi demo berisi enam tim terverifikasi, dua kandidat force majeure, dan satu tim ditolak.',
                'quota' => 32,
                'fee' => 0,
                'registration_start' => now()->subMonth()->toDateString(),
                'registration_end' => now()->addWeek()->toDateString(),
                'team_update_deadline_at' => now()->addWeek(),
                'document_upload_deadline_at' => now()->addWeek(),
                'event_date' => now()->addWeeks(2)->toDateString(),
                'location' => 'Arena Demo BSI Flash',
                'requirements' => [],
                'guides' => [['title' => 'Data Demo', 'content' => 'Khusus pengujian drawing dan force majeure.']],
                'timeline' => [],
                'is_featured' => false,
            ]
        );

        // Menjaga seeder tetap dapat digunakan untuk memulai ulang simulasi pada lomba demo saja.
        $competition->tournamentDraws()->delete();

        $teams = [
            ['Garuda Muda', 'SMA Negeri 1 Jakarta', 'approved', true],
            ['Rajawali Lima', 'SMA Negeri 8 Jakarta', 'approved', true],
            ['Cakrawala Warriors', 'SMK Bina Informatika', 'approved', true],
            ['Nusantara Hoops', 'SMA Pertiwi Bandung', 'approved', true],
            ['Borneo Thunder', 'SMA Negeri 2 Pontianak', 'approved', true],
            ['Celebes Stars', 'SMA Negeri 5 Makassar', 'approved', true],
            ['Merapi Junior', 'SMA Negeri 3 Yogyakarta', 'pending', false],
            ['Samudra Basket', 'SMA Bahari Surabaya', 'revision', true],
            ['Tim Tidak Lolos', 'SMA Contoh Penolakan', 'rejected', true],
        ];

        foreach ($teams as $teamIndex => [$teamName, $schoolName, $status, $reviewed]) {
            $number = $teamIndex + 1;
            $registration = Registration::updateOrCreate(
                ['ticket_code' => 'DEMO-DRAW-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT)],
                [
                    'event_edition_id' => $edition->id,
                    'competition_id' => $competition->id,
                    'full_name' => $teamName.' Captain',
                    'team_name' => $teamName,
                    'whatsapp' => '08129000'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                    'email' => 'demo.draw'.$number.'@example.test',
                    'birth_place' => 'Jakarta',
                    'birth_date' => '2009-01-15',
                    'grade' => 'XI',
                    'nisn' => (string) (9800000000 + $number * 10 + 1),
                    'mother_name' => 'Ibu Demo '.$number,
                    'school_name' => $schoolName,
                    'school_city' => 'Kota Demo '.$number,
                    'school_address' => 'Jl. Pendidikan Demo No. '.$number,
                    'teacher_name' => 'Guru Pendamping '.$number,
                    'teacher_contact' => '08128000'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                    'consent' => true,
                    'status' => $status,
                    'team_completed_at' => now(),
                    'reviewed_by' => $reviewed ? $admin->id : null,
                    'reviewed_at' => $reviewed ? now() : null,
                    'review_note' => $status === 'revision' ? 'Surat rekomendasi asli masih dikonfirmasi sekolah.' : null,
                ]
            );

            foreach (range(1, 5) as $memberOrder) {
                RegistrationMember::updateOrCreate(
                    ['registration_id' => $registration->id, 'member_order' => $memberOrder],
                    [
                        'competition_id' => $competition->id,
                        'full_name' => $teamName.' Pemain '.$memberOrder,
                        'email' => 'demo.team'.$number.'.player'.$memberOrder.'@example.test',
                        'whatsapp' => '08127'.str_pad((string) ($number * 100 + $memberOrder), 7, '0', STR_PAD_LEFT),
                        'nisn' => (string) (9800000000 + $number * 10 + $memberOrder),
                        'birth_place' => 'Jakarta',
                        'birth_date' => '2009-02-'.str_pad((string) min($memberOrder, 28), 2, '0', STR_PAD_LEFT),
                        'grade' => 'XI',
                        'mother_name' => 'Ibu Pemain Demo',
                    ]
                );
            }
        }

        $this->command?->info('Data demo drawing siap: 6 terverifikasi, 2 kandidat force majeure, dan 1 ditolak.');
    }
}
