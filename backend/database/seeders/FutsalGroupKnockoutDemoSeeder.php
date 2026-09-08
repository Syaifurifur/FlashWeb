<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\TournamentController;
use App\Models\Competition;
use App\Models\EventEdition;
use App\Models\Registration;
use App\Models\TournamentDraw;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FutsalGroupKnockoutDemoSeeder extends Seeder
{
    public function run(): void
    {
        $edition = EventEdition::resolveCurrent();
        $competition = Competition::where('event_edition_id', $edition->id)->where('slug', 'futsal-putra')->firstOrFail();
        $session = $competition->sessions()->where('city', 'Bogor')->where('is_active', true)->firstOrFail();
        $admin = User::where('role', 'super_admin')->firstOrFail();
        $teams = Registration::where('competition_id', $competition->id)->where('competition_session_id', $session->id)
            ->where('ticket_code', 'like', 'DEMO-FUTSAL-%')->where('status', 'approved')
            ->whereNotNull('reviewed_by')->whereNotNull('reviewed_at')->orderBy('ticket_code')->get();
        if ($teams->count() !== 16) {
            throw new RuntimeException('Diperlukan 16 tim dari FutsalDemoSeeder yang terverifikasi pada sesi Bogor.');
        }

        $draw = DB::transaction(function () use ($competition, $session, $admin, $teams) {
            Competition::whereKey($competition->id)->lockForUpdate()->firstOrFail();
            // Create a new simulation version; earlier draws and their results remain intact.
            $draw = TournamentDraw::create([
                'competition_id' => $competition->id, 'competition_session_id' => $session->id,
                'operator_id' => $admin->id,
                'version' => (int) $competition->tournamentDraws()->where('competition_session_id', $session->id)->max('version') + 1,
                'mode' => 'manual', 'format' => 'groups_knockout', 'status' => 'locked',
                'drawn_at' => now(), 'locked_at' => now(),
                'settings' => ['group_count' => 4, 'third_place' => true,
                    'demo' => 'futsal-group-knockout-ready',
                    'demo_note' => 'Simulasi 16 tim: fase grup selesai, bagan knockout siap dan belum dimulai.'],
            ]);
            $number = 1;
            $slot = 1;
            foreach ($teams->chunk(4)->values() as $index => $members) {
                $group = 'Grup '.chr(65 + $index);
                $members = $members->values();
                foreach ($members as $team) $draw->entries()->create([
                    'registration_id' => $team->id, 'slot_number' => $slot++, 'group_name' => $group, 'is_bye' => false,
                ]);
                // Deterministic strengths give clear rankings of 9, 6, 3 and 0 points.
                for ($a = 0; $a < 4; $a++) {
                    for ($b = $a + 1; $b < 4; $b++) {
                        $draw->matches()->create([
                            'stage' => 'group', 'group_name' => $group, 'round_label' => $group,
                            'round_number' => 1, 'match_number' => $number++,
                            'participant_a_id' => $members[$a]->id, 'participant_b_id' => $members[$b]->id,
                            'score_a' => 2 + $b - $a, 'score_b' => ($a + $b + $index) % 2,
                            'winner_id' => $members[$a]->id, 'status' => 'completed',
                            'scheduled_at' => now()->subDays(2)->startOfDay()->addHours(8)->addMinutes(($number - 2) * 30),
                            'venue' => $session->venue, 'duration_minutes' => 20,
                        ]);
                    }
                }
            }

            // Use the real automatic drawing rules and payload checks.
            $request = Request::create('/api/manage/tournaments/draws/'.$draw->id.'/knockout', 'POST', ['mode' => 'random']);
            $request->setUserResolver(fn () => $admin);
            $payload = app(TournamentController::class)->generateKnockout($request, $draw);
            if (!$payload->can_redraw_knockout || $payload->matches->where('stage', 'group')->where('status', 'completed')->count() !== 24
                || $payload->matches->whereIn('stage', ['knockout', 'third_place'])->where('status', 'unscheduled')->count() !== 8) {
                throw new RuntimeException('Simulasi belum mencapai kondisi siap drawing ulang. Transaksi dibatalkan.');
            }
            return $payload;
        });

        $this->command?->info('FUTSAL PUTRA - Bogor: drawing versi '.$draw->version.' (ID '.$draw->id.').');
        $this->command?->info('16 tim, 4 grup, 24 laga grup selesai, 8 tim lolos; 7 laga knockout dan 1 perebutan juara ketiga belum dimulai.');
        $this->command?->info('Drawing ulang otomatis/manual tersedia. Riwayat drawing sebelumnya tetap tersimpan.');
    }
}
