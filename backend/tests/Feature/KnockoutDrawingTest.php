<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Registration;
use App\Models\TournamentDraw;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnockoutDrawingTest extends TestCase
{
    use RefreshDatabase;

    private function groupDraw(int $groupCount, bool $completed = true): TournamentDraw
    {
        $admin = User::firstOrCreate(['email' => 'knockout@test.id'], [
            'name' => 'Panitia', 'password' => 'password123', 'role' => 'super_admin',
            'api_token' => hash('sha256', 'knockout-token'),
        ]);
        $competition = Competition::create([
            'title' => 'Knockout '.$groupCount, 'slug' => 'knockout-'.$groupCount, 'category' => 'Science Competition',
            'short_description' => 'Test', 'description' => 'Test', 'quota' => 100, 'fee' => 0,
            'registration_start' => now()->subDay(), 'registration_end' => now()->addDays(10),
            'event_date' => now()->addDays(20), 'location' => 'Online', 'requirements' => [], 'timeline' => [],
        ]);
        $groups = [];
        foreach (range(1, $groupCount * 3) as $number) {
            $participant = Registration::create([
                'competition_id' => $competition->id, 'ticket_code' => 'KO-'.$groupCount.'-'.$number,
                'full_name' => 'Tim '.str_pad($number, 2, '0', STR_PAD_LEFT), 'whatsapp' => '081234567890',
                'email' => 'tim'.$number.'@test.id', 'school_name' => 'Sekolah '.$number,
                'consent' => true, 'status' => 'approved',
            ]);
            $groups[(int) floor(($number - 1) / 3)][] = $participant->id;
        }
        $response = $this->withToken('knockout-token')->postJson('/api/manage/tournaments/competitions/'.$competition->id.'/draw', [
            'mode' => 'manual', 'format' => 'groups_knockout', 'group_count' => $groupCount, 'manual_groups' => $groups,
        ])->assertCreated();
        $draw = TournamentDraw::findOrFail($response->json('id'));
        if ($completed) {
            foreach ($draw->matches as $match) $match->update([
                'score_a' => 2, 'score_b' => 0, 'winner_id' => $match->participant_a_id, 'status' => 'completed',
            ]);
        }
        $this->assertSame($admin->id, $draw->operator_id);
        return $draw;
    }

    public function test_redraw_switches_modes_preserves_groups_and_replaces_knockout_schedules_with_byes(): void
    {
        $draw = $this->groupDraw(3);
        $draw->update(['settings' => [...$draw->settings, 'third_place' => true], 'status' => 'locked']);
        $groupSnapshot = $draw->matches()->get()->map(fn ($match) => $match->attributesToArray())->all();
        $endpoint = '/api/manage/tournaments/draws/'.$draw->id.'/knockout';
        $first = $this->withToken('knockout-token')->postJson($endpoint)->assertOk()->assertJsonPath('can_redraw_knockout', true);
        $oldMatches = $draw->matches()->whereIn('stage', ['knockout', 'third_place'])->get();
        $oldMatches->firstWhere('status', 'unscheduled')->update(['status' => 'upcoming', 'scheduled_at' => now()->addDay(), 'venue' => 'Lapangan Lama']);
        $slots = array_reverse($first->json('settings.knockout.slots'));
        $manual = $this->withToken('knockout-token')->postJson($endpoint, [
            'redraw' => true, 'knockout_version' => 1, 'mode' => 'manual', 'manual_slots' => $slots,
        ])->assertOk()->assertJsonPath('settings.knockout.version', 2)->assertJsonPath('settings.knockout.mode', 'manual')
            ->assertJsonCount(1, 'settings.knockout_history')->assertJsonPath('can_redraw_knockout', true);
        $this->assertSame($slots, $manual->json('settings.knockout.slots'));
        $this->assertSame($slots, collect($manual->json('matches'))->where('stage', 'knockout')->where('round_number', 1)
            ->flatMap(fn ($match) => [$match['participant_a_id'], $match['participant_b_id']])->all());
        foreach ($oldMatches as $match) $this->assertDatabaseMissing('tournament_matches', ['id' => $match->id]);
        $this->assertSame(1, $draw->matches()->where('stage', 'third_place')->count());
        $this->assertSame(0, $draw->matches()->whereIn('stage', ['knockout', 'third_place'])->whereNotNull('scheduled_at')->count());
        $this->assertSame($groupSnapshot, $draw->matches()->where('stage', 'group')->get()->map(fn ($match) => $match->attributesToArray())->all());
        $matchIds = $draw->matches()->pluck('id')->all();
        $this->withToken('knockout-token')->postJson($endpoint, ['redraw' => true, 'knockout_version' => 1, 'mode' => 'random'])->assertUnprocessable();
        $this->withToken('knockout-token')->postJson($endpoint, ['redraw' => true, 'knockout_version' => 2, 'mode' => 'manual', 'manual_slots' => [999]])->assertUnprocessable();
        $this->assertSame($matchIds, $draw->matches()->pluck('id')->all());
        $this->withToken('knockout-token')->postJson($endpoint, ['redraw' => true, 'knockout_version' => 2, 'mode' => 'random'])
            ->assertOk()->assertJsonPath('settings.knockout.version', 3)->assertJsonPath('settings.knockout.mode', 'random')
            ->assertJsonCount(2, 'settings.knockout_history');
    }

    public function test_redraw_remains_blocked_after_starting_from_either_match_editor_even_if_status_is_reset(): void
    {
        foreach (['tournaments', 'schedules'] as $index => $editor) {
            $draw = $this->groupDraw($index + 2);
            $endpoint = '/api/manage/tournaments/draws/'.$draw->id.'/knockout';
            $first = $this->withToken('knockout-token')->postJson($endpoint)->assertOk();
            $match = $draw->matches()->where('stage', 'knockout')->whereNotNull('participant_a_id')->whereNotNull('participant_b_id')->firstOrFail();
            $this->withToken('knockout-token')->putJson('/api/manage/'.$editor.'/matches/'.$match->id, [
                'status' => 'ongoing', 'duration_minutes' => 60, 'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'), 'venue' => 'Lapangan A',
            ])->assertOk();
            $this->assertNotEmpty($draw->fresh()->settings['knockout_started_at']);
            $this->withToken('knockout-token')->putJson('/api/manage/'.$editor.'/matches/'.$match->id, [
                'status' => 'unscheduled', 'duration_minutes' => 60, 'score_a' => null, 'score_b' => null,
            ])->assertOk();
            $matchIds = $draw->matches()->pluck('id')->all();
            foreach (['random', 'manual'] as $mode) {
                $this->withToken('knockout-token')->postJson($endpoint, [
                    'redraw' => true, 'knockout_version' => 1, 'mode' => $mode, 'manual_slots' => $first->json('settings.knockout.slots'),
                ])->assertUnprocessable();
            }
            $this->assertSame($matchIds, $draw->matches()->pluck('id')->all());
            $this->withToken('knockout-token')->getJson('/api/manage/tournaments?competition_id='.$draw->competition_id)
                ->assertOk()->assertJsonPath('draw.can_redraw_knockout', false);
        }
    }

    public function test_legacy_results_and_third_place_matches_also_prevent_redraw(): void
    {
        $draw = $this->groupDraw(2);
        $draw->update(['settings' => [...$draw->settings, 'third_place' => true]]);
        $endpoint = '/api/manage/tournaments/draws/'.$draw->id.'/knockout';
        $this->withToken('knockout-token')->postJson($endpoint)->assertOk();
        foreach (['knockout', 'third_place'] as $stage) {
            $match = $draw->matches()->where('stage', $stage)->firstOrFail();
            // Simulate historical data without the new permanent start marker.
            foreach ([['status' => 'ongoing'], ['status' => 'completed'], ['status' => 'walkover'], ['score_a' => 0], ['set_scores' => [['score_a' => 1]]]] as $evidence) {
                $draw->matches()->whereKey($match->id)->update($evidence);
                $this->withToken('knockout-token')->postJson($endpoint, ['redraw' => true, 'knockout_version' => 1, 'mode' => 'random'])->assertUnprocessable();
                $draw->matches()->whereKey($match->id)->update(['status' => 'unscheduled', 'score_a' => null, 'set_scores' => null]);
            }
        }
        $draw->matches()->whereKey($match->id)->update(['status' => 'ongoing']);
        $match->refresh()->update(['status' => 'unscheduled']);
        $this->assertNotEmpty($draw->fresh()->settings['knockout_started_at']);
        $this->withToken('knockout-token')->postJson($endpoint, ['redraw' => true, 'knockout_version' => 1, 'mode' => 'random'])->assertUnprocessable();
    }

    private function standings(TournamentDraw $draw): array
    {
        return $this->withToken('knockout-token')->getJson('/api/manage/tournaments?competition_id='.$draw->competition_id)
            ->assertOk()->json('draw.group_standings');
    }

    public function test_automatic_draw_pairs_winners_with_other_group_runners_up_and_resolves_byes(): void
    {
        foreach ([2, 3, 4, 5] as $groupCount) {
            $draw = $this->groupDraw($groupCount);
            $ranks = [];
            foreach ($this->standings($draw) as $group) {
                foreach (array_slice($group['rows'], 0, 2) as $row) $ranks[$row['registration_id']] = [$group['name'], $row['position']];
            }
            // Existing callers without a mode continue to use automatic drawing.
            $response = $this->withToken('knockout-token')->postJson('/api/manage/tournaments/draws/'.$draw->id.'/knockout')
                ->assertOk()->assertJsonPath('settings.knockout.mode', 'random');
            $firstRound = collect($response->json('matches'))->where('stage', 'knockout')->where('round_number', 1);
            $ids = $firstRound->flatMap(fn ($match) => [$match['participant_a_id'], $match['participant_b_id']])->filter()->sort()->values()->all();
            $expected = array_keys($ranks);
            sort($expected);
            $this->assertSame($expected, $ids);
            foreach (collect($response->json('matches'))->where('stage', 'knockout') as $match) {
                if ($match['status'] === 'bye') {
                    $this->assertNotNull($match['winner_id']);
                } elseif ($match['participant_a_id'] && $match['participant_b_id']) {
                    $a = $ranks[$match['participant_a_id']];
                    $b = $ranks[$match['participant_b_id']];
                    $this->assertNotSame($a[0], $b[0]);
                    $this->assertSame(1, $a[1]);
                    $this->assertSame(2, $b[1]);
                }
            }
            $count = $draw->matches()->count();
            $this->withToken('knockout-token')->postJson('/api/manage/tournaments/draws/'.$draw->id.'/knockout')->assertUnprocessable();
            $this->assertSame($count, $draw->matches()->count());
        }
    }

    public function test_both_modes_require_the_entire_group_phase_to_finish(): void
    {
        $draw = $this->groupDraw(2, false);
        $firstGroup = $draw->matches->first()->group_name;
        foreach ($draw->matches->where('group_name', $firstGroup) as $match) $match->update(['status' => 'completed', 'score_a' => 2, 'score_b' => 0]);
        foreach (['random', 'manual'] as $mode) {
            $this->withToken('knockout-token')->postJson('/api/manage/tournaments/draws/'.$draw->id.'/knockout', [
                'mode' => $mode, 'manual_slots' => $draw->entries->take(4)->pluck('registration_id')->all(),
            ])->assertUnprocessable();
        }
        $this->assertSame(0, $draw->matches()->where('stage', 'knockout')->count());
    }

    public function test_manual_draw_accepts_only_all_top_two_teams_once_and_preserves_committee_positions(): void
    {
        $draw = $this->groupDraw(3);
        $standings = $this->standings($draw);
        $ids = collect($standings)->flatMap(fn ($group) => collect($group['rows'])->take(2)->pluck('registration_id'))->all();
        $slots = [$ids[0], $ids[2], $ids[4], null, $ids[1], $ids[3], $ids[5], null];
        $duplicate = $slots; $duplicate[0] = $ids[2];
        $third = $slots; $third[0] = $standings[0]['rows'][2]['registration_id'];
        $outsider = $slots; $outsider[0] = 999999;
        $missing = $slots; $missing[0] = null;
        foreach ([$duplicate, $third, $outsider, $missing, array_slice($slots, 0, 4), [...$ids, null, null]] as $invalid) {
            $this->withToken('knockout-token')->postJson('/api/manage/tournaments/draws/'.$draw->id.'/knockout', [
                'mode' => 'manual', 'manual_slots' => $invalid,
            ])->assertUnprocessable();
            $this->assertSame(0, $draw->matches()->where('stage', 'knockout')->count());
        }
        $response = $this->withToken('knockout-token')->postJson('/api/manage/tournaments/draws/'.$draw->id.'/knockout', [
            'mode' => 'manual', 'manual_slots' => $slots,
        ])->assertOk()->assertJsonPath('settings.knockout.mode', 'manual')
            ->assertJsonPath('settings.knockout.created_by', $draw->operator_id);
        $this->assertSame($slots, collect($response->json('matches'))->where('stage', 'knockout')->where('round_number', 1)
            ->flatMap(fn ($match) => [$match['participant_a_id'], $match['participant_b_id']])->all());
    }
}
