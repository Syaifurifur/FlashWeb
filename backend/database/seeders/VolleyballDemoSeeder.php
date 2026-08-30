<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\EventEdition;
use App\Models\Registration;
use App\Models\RegistrationMember;
use App\Models\RegistrationOfficial;
use App\Models\TournamentDraw;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VolleyballDemoSeeder extends Seeder
{
    private const TARGETS = [
        'bola-voli-putra' => [
            ['Garuda Pajajaran', 'SMAN 1 Bogor'],
            ['Rajawali Muda', 'SMAN 2 Bogor'],
            ['Kujang Volley Club', 'SMAN 3 Bogor'],
            ['Salak Spikers', 'SMAN 4 Bogor'],
            ['Bintang Cibinong', 'SMAN 1 Cibinong'],
            ['Vokasi Smash', 'SMKN 1 Bogor'],
            ['Kesatuan Volleyball', 'SMA Kesatuan Bogor'],
            ['Pertiwi Thunder', 'SMA Pertiwi Bogor'],
        ],
        'bola-voli-putri' => [
            ['Pajajaran Queens', 'SMAN 5 Bogor'],
            ['Bogor Sakura', 'SMAN 6 Bogor'],
            ['Cakrawala Putri', 'SMAN 7 Bogor'],
            ['Kujang Srikandi', 'SMAN 8 Bogor'],
            ['Bintang Pertiwi', 'SMAN 9 Bogor'],
            ['Vokasi Angels', 'SMKN 2 Bogor'],
            ['Kesatuan Phoenix', 'SMA Kesatuan Bogor'],
            ['Mandiri Volley Girls', 'SMA Mandiri Bogor'],
        ],
    ];

    public function run(): void
    {
        $edition = EventEdition::resolveCurrent();
        $admin = User::where('role', 'super_admin')->first();
        if (! $admin) throw new RuntimeException('Akun super admin diperlukan untuk membuat dummy voli.');

        foreach (self::TARGETS as $slug => $teams) {
            $competition = Competition::query()
                ->where('event_edition_id', $edition->id)
                ->where('slug', $slug)
                ->with(['sessions' => fn ($query) => $query->where('is_active', true)->orderByRaw("CASE WHEN city = 'Bogor' THEN 0 ELSE 1 END")])
                ->first();
            if (! $competition) throw new RuntimeException("Lomba {$slug} pada tahun aktif tidak ditemukan.");
            $session = $competition->sessions->first();
            if (! $session) throw new RuntimeException("Sesi kota aktif untuk {$competition->title} tidak ditemukan.");

            DB::transaction(function () use ($admin, $competition, $edition, $session, $slug, $teams) {
                $registrations = collect();
                foreach ($teams as $teamIndex => [$teamName, $schoolName]) {
                    $number = $teamIndex + 1;
                    $prefix = $slug === 'bola-voli-putra' ? 'VP' : 'VW';
                    $ticket = "DEMO-{$prefix}-BGR-".str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                    $registration = Registration::updateOrCreate(
                        ['ticket_code'=>$ticket],
                        [
                            'event_edition_id'=>$edition->id,
                            'competition_id'=>$competition->id,
                            'competition_session_id'=>$session->id,
                            'full_name'=>$teamName.' Captain',
                            'team_name'=>$teamName,
                            'whatsapp'=>'081350'.str_pad((string) (($competition->id * 100) + $number), 6, '0', STR_PAD_LEFT),
                            'email'=>str_replace('-', '.', $slug).'.'.$number.'@demo.test',
                            'birth_place'=>'Bogor','birth_date'=>'2009-03-15','grade'=>'XI',
                            'nisn'=>(string) (9800000000 + $competition->id * 100 + $number),
                            'mother_name'=>'Ibu Kapten Voli '.$number,
                            'school_name'=>$schoolName,'school_city'=>'Bogor','school_address'=>'Jl. Olahraga Bogor No. '.$number,
                            'school_logo_path'=>"demo/volleyball/{$slug}/logo-{$number}.png",
                            'teacher_name'=>'Guru Voli '.$number,'teacher_contact'=>'081360'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                            'student_card_path'=>"demo/volleyball/{$slug}/kartu-{$number}.pdf",
                            'delegation_letter_path'=>"demo/volleyball/{$slug}/delegasi-{$number}.pdf",
                            'statement_letter_path'=>"demo/volleyball/{$slug}/pernyataan-{$number}.pdf",
                            'photo_path'=>"demo/volleyball/{$slug}/foto-{$number}.png",
                            'payment_proof_path'=>"demo/volleyball/{$slug}/pembayaran-{$number}.pdf",
                            'consent'=>true,'status'=>'approved','team_completed_at'=>now(),'documents_completed_at'=>now(),
                            'payment_verified_at'=>now(),'payment_verified_by'=>$admin->id,
                            'reviewed_by'=>$admin->id,'reviewed_at'=>now(),
                            'review_note'=>'Data dummy pertandingan voli untuk simulasi skor per set.',
                        ]
                    );
                    $this->seedRoster($registration, $competition, $admin, $number, $slug, $teamName);
                    $registrations->push($registration);
                }

                $draw = $competition->tournamentDraws()
                    ->where('competition_session_id', $session->id)
                    ->get()
                    ->first(fn (TournamentDraw $item) => ($item->settings['demo_seed'] ?? null) === 'volleyball');
                if (! $draw) {
                    $draw = $competition->tournamentDraws()->create([
                        'competition_session_id'=>$session->id,'operator_id'=>$admin->id,
                        'version'=>(int) $competition->tournamentDraws()->where('competition_session_id',$session->id)->max('version') + 1,
                        'mode'=>'manual','format'=>'single_elimination','settings'=>[],
                        'status'=>'locked','drawn_at'=>now(),'locked_at'=>now(),
                    ]);
                }
                $draw->entries()->delete();
                $draw->matches()->delete();
                $draw->update([
                    'operator_id'=>$admin->id,'mode'=>'manual','format'=>'single_elimination',
                    'settings'=>['third_place'=>true,'demo_seed'=>'volleyball','description'=>'Dummy alur skor set voli'],
                    'status'=>'locked','drawn_at'=>now(),'locked_at'=>now(),
                ]);
                foreach ($registrations as $index => $registration) $draw->entries()->create([
                    'registration_id'=>$registration->id,'slot_number'=>$index + 1,'is_bye'=>false,
                ]);

                $venue = $session->schedule_venues[0] ?? $session->venue ?? 'Lapangan Voli';
                $quarterfinals = [];
                foreach (range(0, 3) as $index) {
                    $participantA = $registrations[$index * 2];
                    $participantB = $registrations[$index * 2 + 1];
                    $attributes = [
                        'stage'=>'main','round_number'=>1,'round_label'=>'Quarterfinal','match_number'=>$index + 1,
                        'participant_a_id'=>$participantA->id,'participant_b_id'=>$participantB->id,
                        'duration_minutes'=>90,
                    ];
                    if ($index === 0) $attributes += [
                        'status'=>'completed','scheduled_at'=>now()->subHours(2),'venue'=>$venue,
                        'best_of_sets'=>3,'set_scores'=>[
                            ['score_a'=>25,'score_b'=>18,'completed'=>true],
                            ['score_a'=>25,'score_b'=>21,'completed'=>true],
                        ],'score_a'=>2,'score_b'=>0,'winner_id'=>$participantA->id,
                    ];
                    elseif ($index === 1) $attributes += [
                        'status'=>'ongoing','scheduled_at'=>now()->subMinutes(25),'venue'=>$venue,
                        'best_of_sets'=>3,'set_scores'=>[
                            ['score_a'=>25,'score_b'=>22,'completed'=>true],
                            ['score_a'=>12,'score_b'=>12,'completed'=>false],
                            ['score_a'=>null,'score_b'=>null,'completed'=>false],
                        ],'score_a'=>1,'score_b'=>0,
                    ];
                    elseif ($index === 2) $attributes += ['status'=>'unscheduled'];
                    else $attributes += ['status'=>'upcoming','scheduled_at'=>now()->addHours(2),'venue'=>$venue];
                    $quarterfinals[] = $draw->matches()->create($attributes);
                }

                $semifinalOne = $draw->matches()->create([
                    'stage'=>'main','round_number'=>2,'round_label'=>'Semifinal','match_number'=>5,
                    'participant_a_id'=>$quarterfinals[0]->winner_id,
                    'source_a_match_id'=>$quarterfinals[0]->id,'source_a_outcome'=>'winner',
                    'source_b_match_id'=>$quarterfinals[1]->id,'source_b_outcome'=>'winner','status'=>'unscheduled','duration_minutes'=>90,
                ]);
                $semifinalTwo = $draw->matches()->create([
                    'stage'=>'main','round_number'=>2,'round_label'=>'Semifinal','match_number'=>6,
                    'source_a_match_id'=>$quarterfinals[2]->id,'source_a_outcome'=>'winner',
                    'source_b_match_id'=>$quarterfinals[3]->id,'source_b_outcome'=>'winner','status'=>'unscheduled','duration_minutes'=>90,
                ]);
                $draw->matches()->create([
                    'stage'=>'main','round_number'=>3,'round_label'=>'Final','match_number'=>7,
                    'source_a_match_id'=>$semifinalOne->id,'source_a_outcome'=>'winner',
                    'source_b_match_id'=>$semifinalTwo->id,'source_b_outcome'=>'winner','status'=>'unscheduled','duration_minutes'=>90,
                ]);
                $draw->matches()->create([
                    'stage'=>'third_place','round_number'=>3,'round_label'=>'Perebutan Juara Ketiga','match_number'=>8,
                    'source_a_match_id'=>$semifinalOne->id,'source_a_outcome'=>'loser',
                    'source_b_match_id'=>$semifinalTwo->id,'source_b_outcome'=>'loser','status'=>'unscheduled','duration_minutes'=>90,
                ]);

                $this->command?->info("{$competition->title}: 8 tim dan 8 pertandingan dummy siap di {$session->city}.");
            });
        }
    }

    private function seedRoster(Registration $registration, Competition $competition, User $admin, int $teamNumber, string $slug, string $teamName): void
    {
        if ($competition->participation_type !== 'team') return;
        $teamSize = max(2, (int) $competition->team_size);
        $officialCount = max(0, (int) $competition->official_count);
        $registration->members()->where('member_order', '>', $teamSize)->delete();
        foreach (range(1, $teamSize) as $memberOrder) RegistrationMember::updateOrCreate(
            ['registration_id'=>$registration->id,'member_order'=>$memberOrder],
            [
                'competition_id'=>$competition->id,'full_name'=>$teamName.' Pemain '.$memberOrder,
                'email'=>str_replace('-', '.', $slug).'.'.$teamNumber.'.p'.$memberOrder.'@demo.test',
                'whatsapp'=>'08137'.str_pad((string) ($teamNumber * 100 + $memberOrder), 7, '0', STR_PAD_LEFT),
                'nisn'=>(string) (9900000000 + $competition->id * 10000 + $teamNumber * 100 + $memberOrder),
                'birth_place'=>'Bogor','birth_date'=>'2009-04-'.str_pad((string) min($memberOrder,28),2,'0',STR_PAD_LEFT),
                'grade'=>'XI','mother_name'=>'Ibu Pemain '.$teamNumber.'-'.$memberOrder,
                'student_card_path'=>"demo/volleyball/{$slug}/kartu-{$teamNumber}-{$memberOrder}.pdf",
                'photo_path'=>"demo/volleyball/{$slug}/foto-{$teamNumber}-{$memberOrder}.png",
                'nisn_verified_at'=>now(),'nisn_verified_by'=>$admin->id,
            ]
        );
        $registration->officials()->where('official_order', '>', $officialCount)->delete();
        if ($officialCount > 0) foreach (range(1, $officialCount) as $officialOrder) RegistrationOfficial::updateOrCreate(
                ['registration_id'=>$registration->id,'official_order'=>$officialOrder],
                ['full_name'=>$teamName.' Official '.$officialOrder,'position'=>$officialOrder === 1 ? 'Pelatih' : ($officialOrder === 2 ? 'Asisten Pelatih' : 'Manajer Tim'),'whatsapp'=>'08138'.str_pad((string) ($teamNumber * 10 + $officialOrder),7,'0',STR_PAD_LEFT)]
            );
    }
}
