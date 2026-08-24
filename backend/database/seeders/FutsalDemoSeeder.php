<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\EventEdition;
use App\Models\Registration;
use App\Models\RegistrationMember;
use App\Models\RegistrationOfficial;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FutsalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $edition = EventEdition::resolveCurrent();
        $competition = Competition::query()
            ->where('event_edition_id', $edition->id)
            ->where('slug', 'futsal-putra')
            ->with(['sessions' => fn ($query) => $query->where('is_active', true)])
            ->first();

        if (! $competition) {
            throw new RuntimeException('Lomba FUTSAL PUTRA pada tahun aktif tidak ditemukan.');
        }

        if ($competition->participation_type !== 'team') {
            throw new RuntimeException('Lomba FUTSAL PUTRA harus menggunakan format peserta tim.');
        }

        $session = $competition->sessions->first();
        if (! $session) {
            throw new RuntimeException('Sesi kota aktif untuk FUTSAL PUTRA tidak ditemukan.');
        }

        $admin = User::where('role', 'super_admin')->firstOrFail();
        $teamSize = max(2, (int) $competition->team_size);
        $officialCount = max(0, (int) $competition->official_count);
        $teams = [
            ['Garuda Muda FC', 'SMAN 1 Bogor'],
            ['Rajawali Bogor', 'SMAN 2 Bogor'],
            ['Pajajaran United', 'SMAN 3 Bogor'],
            ['Kujang Muda', 'SMAN 4 Bogor'],
            ['Salak Warriors', 'SMAN 5 Bogor'],
            ['Bintang Selatan FC', 'SMAN 6 Bogor'],
            ['Cakrawala Futsal', 'SMAN 7 Bogor'],
            ['Elang Nusantara', 'SMAN 8 Bogor'],
            ['Vokasi United', 'SMKN 1 Bogor'],
            ['Teknika Muda', 'SMKN 2 Bogor'],
            ['Bina Prestasi FC', 'SMA Bina Insani Bogor'],
            ['Kesatuan Futsal', 'SMA Kesatuan Bogor'],
            ['Pertiwi Muda', 'SMA Pertiwi Bogor'],
            ['Tunas Harapan FC', 'SMA Tunas Harapan Bogor'],
            ['Metro City Futsal', 'SMA Metro Bogor'],
            ['Satria Mandiri', 'SMA Mandiri Bogor'],
        ];

        DB::transaction(function () use ($admin, $competition, $edition, $officialCount, $session, $teamSize, $teams) {
            foreach ($teams as $teamIndex => [$teamName, $schoolName]) {
                $teamNumber = $teamIndex + 1;
                $ticket = 'DEMO-FUTSAL-'.str_pad((string) $teamNumber, 2, '0', STR_PAD_LEFT);
                $registration = Registration::updateOrCreate(
                    ['ticket_code' => $ticket],
                    [
                        'event_edition_id' => $edition->id,
                        'competition_id' => $competition->id,
                        'competition_session_id' => $session->id,
                        'full_name' => $teamName.' Captain',
                        'team_name' => $teamName,
                        'whatsapp' => '081310'.str_pad((string) $teamNumber, 6, '0', STR_PAD_LEFT),
                        'email' => 'captain.futsal'.str_pad((string) $teamNumber, 2, '0', STR_PAD_LEFT).'@demo.test',
                        'birth_place' => 'Bogor',
                        'birth_date' => '2009-01-15',
                        'grade' => 'XI',
                        'nisn' => (string) (9600000000 + $teamNumber),
                        'mother_name' => 'Ibu Kapten '.$teamNumber,
                        'school_name' => $schoolName,
                        'school_city' => 'Bogor',
                        'school_address' => 'Jl. Pendidikan Bogor No. '.$teamNumber,
                        'school_logo_path' => 'demo/futsal/logo-'.$teamNumber.'.png',
                        'teacher_name' => 'Guru Pendamping '.$teamNumber,
                        'teacher_contact' => '081320'.str_pad((string) $teamNumber, 6, '0', STR_PAD_LEFT),
                        'student_card_path' => 'demo/futsal/kartu-kapten-'.$teamNumber.'.pdf',
                        'delegation_letter_path' => 'demo/futsal/delegasi-'.$teamNumber.'.pdf',
                        'statement_letter_path' => 'demo/futsal/pernyataan-'.$teamNumber.'.pdf',
                        'photo_path' => 'demo/futsal/foto-kapten-'.$teamNumber.'.png',
                        'payment_proof_path' => 'demo/futsal/pembayaran-'.$teamNumber.'.pdf',
                        'consent' => true,
                        'status' => 'approved',
                        'team_completed_at' => now(),
                        'documents_completed_at' => now(),
                        'payment_verified_at' => now(),
                        'payment_verified_by' => $admin->id,
                        'reviewed_by' => $admin->id,
                        'reviewed_at' => now(),
                        'review_note' => 'Data dummy FUTSAL PUTRA siap untuk simulasi drawing dan jadwal.',
                    ]
                );

                $registration->members()->where('member_order', '>', $teamSize)->delete();
                foreach (range(1, $teamSize) as $memberOrder) {
                    RegistrationMember::updateOrCreate(
                        ['registration_id' => $registration->id, 'member_order' => $memberOrder],
                        [
                            'competition_id' => $competition->id,
                            'full_name' => $teamName.' Pemain '.$memberOrder,
                            'email' => 'futsal'.$teamNumber.'.pemain'.$memberOrder.'@demo.test',
                            'whatsapp' => '08133'.str_pad((string) ($teamNumber * 100 + $memberOrder), 7, '0', STR_PAD_LEFT),
                            'nisn' => (string) (9700000000 + $teamNumber * 100 + $memberOrder),
                            'birth_place' => 'Bogor',
                            'birth_date' => '2009-02-'.str_pad((string) min($memberOrder, 28), 2, '0', STR_PAD_LEFT),
                            'grade' => 'XI',
                            'mother_name' => 'Ibu Pemain '.$teamNumber.'-'.$memberOrder,
                            'student_card_path' => 'demo/futsal/kartu-'.$teamNumber.'-'.$memberOrder.'.pdf',
                            'photo_path' => 'demo/futsal/foto-'.$teamNumber.'-'.$memberOrder.'.png',
                            'nisn_verified_at' => now(),
                            'nisn_verified_by' => $admin->id,
                        ]
                    );
                }

                $registration->officials()->where('official_order', '>', $officialCount)->delete();
                if ($officialCount > 0) {
                    foreach (range(1, $officialCount) as $officialOrder) {
                        RegistrationOfficial::updateOrCreate(
                            ['registration_id' => $registration->id, 'official_order' => $officialOrder],
                            [
                                'full_name' => $teamName.' Official '.$officialOrder,
                                'position' => $officialOrder === 1 ? 'Pelatih' : ($officialOrder === 2 ? 'Asisten Pelatih' : 'Manajer Tim'),
                                'whatsapp' => '08134'.str_pad((string) ($teamNumber * 10 + $officialOrder), 7, '0', STR_PAD_LEFT),
                            ]
                        );
                    }
                }
            }
        });

        $this->command?->info(
            count($teams).' tim dummy FUTSAL PUTRA siap di '.$session->city.' untuk drawing, jadwal, dan TV Mode.'
        );
    }
}
