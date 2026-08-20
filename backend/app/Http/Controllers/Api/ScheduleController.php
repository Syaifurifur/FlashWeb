<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionNotification;
use App\Models\TournamentDraw;
use App\Models\TournamentMatch;
use App\Models\TournamentScheduleBlock;
use App\Models\EventEdition;
use App\Models\CompetitionResult;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    private const STATUSES = ['unscheduled','upcoming','check_in','ongoing','delayed','completed','walkover','cancelled','bye'];

    private function competitions(Request $request)
    {
        return $request->user()->manageableCompetitionsQuery(EventEdition::resolveCurrent()->id);
    }

    private function authorizeCompetition(Request $request, Competition $competition): void
    {
        abort_unless($this->competitions($request)->whereKey($competition->id)->exists(), 403);
    }

    private function venues(Competition $competition): array
    {
        return $competition->schedule_venues ?: ['Lapangan 1', 'Lapangan 2', 'Lapangan 3'];
    }

    private function matchQuery(TournamentDraw $draw)
    {
        return $draw->matches()->with([
            'participantA:id,full_name,team_name,school_name',
            'participantB:id,full_name,team_name,school_name',
            'winner:id,full_name,team_name,school_name',
        ])->orderBy('match_number');
    }

    private function payload(Competition $competition, ?TournamentDraw $draw): array
    {
        $matches = $draw ? $this->matchQuery($draw)->get() : collect();
        $blocks = $competition->scheduleBlocks()->when($draw, fn ($query) => $query->where(function ($q) use ($draw) {
            $q->whereNull('tournament_draw_id')->orWhere('tournament_draw_id', $draw->id);
        }))->orderBy('starts_at')->get();

        return [
            'competition' => [...$competition->only(['id','title','slug','category','event_date']), 'venues' => $this->venues($competition)],
            'draw' => $draw?->only(['id','version','format','status','locked_at']),
            'matches' => $matches,
            'blocks' => $blocks,
            'conflicts' => $this->allConflicts($matches, $blocks),
            'statuses' => self::STATUSES,
        ];
    }

    public function manage(Request $request)
    {
        $options = $this->competitions($request)->orderBy('title')->get(['id','title','slug','category']);
        $competition = $request->filled('competition_id')
            ? $this->competitions($request)->whereKey($request->integer('competition_id'))->firstOrFail()
            : $this->competitions($request)->orderBy('title')->first();
        if (!$competition) return ['competitions' => $options, 'competition' => null, 'draw' => null, 'matches' => [], 'blocks' => [], 'conflicts' => []];
        $draw = $competition->tournamentDraws()->latest('version')->first();
        return ['competitions' => $options, ...$this->payload($competition, $draw)];
    }

    public function configureVenues(Request $request, Competition $competition)
    {
        $this->authorizeCompetition($request, $competition);
        $data = $request->validate(['venues' => 'required|array|min:1|max:20', 'venues.*' => 'required|string|max:160|distinct']);
        $competition->update(['schedule_venues' => array_values($data['venues'])]);
        return $this->payload($competition->fresh(), $competition->tournamentDraws()->latest('version')->first());
    }

    public function updateMatch(Request $request, TournamentMatch $match)
    {
        $match->load('tournamentDraw.competition');
        $competition = $match->tournamentDraw->competition;
        $this->authorizeCompetition($request, $competition);
        $data = $request->validate([
            'scheduled_at' => 'nullable|date', 'venue' => 'nullable|string|max:160',
            'duration_minutes' => 'required|integer|min:5|max:720',
            'status' => 'required|in:'.implode(',', self::STATUSES),
            'score_a' => 'nullable|numeric|min:0', 'score_b' => 'nullable|numeric|min:0',
            'winner_id' => 'nullable|integer', 'force' => 'sometimes|boolean', 'notify' => 'sometimes|boolean',
        ]);

        if ($data['status'] === 'unscheduled') {
            $data['scheduled_at'] = null;
            $data['venue'] = null;
        } elseif (!in_array($data['status'], ['cancelled','bye'], true)) {
            abort_unless(!empty($data['scheduled_at']) && !empty($data['venue']), 422, 'Waktu dan lapangan harus diisi untuk status pertandingan ini.');
        }

        if ($data['status'] === 'completed') {
            abort_unless($match->participant_a_id && $match->participant_b_id, 422, 'Peserta pertandingan belum lengkap.');
            abort_unless(array_key_exists('score_a', $data) && array_key_exists('score_b', $data), 422, 'Skor kedua peserta harus diisi.');
            if ((float)$data['score_a'] === (float)$data['score_b']) {
                abort_if($match->stage !== 'group', 422, 'Skor pertandingan gugur tidak boleh seri.');
                $data['winner_id'] = null;
            } else $data['winner_id'] = (float)$data['score_a'] > (float)$data['score_b'] ? $match->participant_a_id : $match->participant_b_id;
        } elseif ($data['status'] === 'walkover') {
            abort_unless(in_array((int)($data['winner_id'] ?? 0), [$match->participant_a_id, $match->participant_b_id], true), 422, 'Pemenang walkover harus salah satu peserta pertandingan.');
        } elseif ($data['status'] !== 'bye') $data['winner_id'] = null;

        if (!empty($data['scheduled_at']) && !empty($data['venue']) && !($data['force'] ?? false)) {
            $messages = $this->candidateConflicts($match, $data);
            if ($messages) return response()->json(['message' => 'Jadwal berbenturan.', 'conflicts' => $messages], 422);
        }

        $notify = (bool)($data['notify'] ?? false);
        unset($data['force'], $data['notify']);
        $match->update($data);
        if (in_array($match->status, ['completed','walkover','bye'], true)) (new TournamentController)->resolveDependents($match);
        if (in_array($match->status, ['completed','walkover'], true) && $match->winner_id && preg_match('/\bfinal\b/i', $match->round_label)) {
            $loserId = $match->winner_id === $match->participant_a_id ? $match->participant_b_id : $match->participant_a_id;
            CompetitionResult::updateOrCreate(
                ['competition_id'=>$competition->id, 'competition_session_id'=>null, 'rank'=>1, 'source'=>'tournament'],
                ['registration_id'=>$match->winner_id, 'title'=>'Juara 1', 'announced_at'=>now()]
            );
            if ($loserId) CompetitionResult::updateOrCreate(
                ['competition_id'=>$competition->id, 'competition_session_id'=>null, 'rank'=>2, 'source'=>'tournament'],
                ['registration_id'=>$loserId, 'title'=>'Juara 2', 'announced_at'=>now()]
            );
        }

        if ($notify) $this->notifyParticipants($request, $competition, $match);
        return $this->payload($competition->fresh(), $match->tournamentDraw->fresh());
    }

    public function storeBlock(Request $request, Competition $competition)
    {
        $this->authorizeCompetition($request, $competition);
        $data = $this->validateBlock($request);
        $draw = $competition->tournamentDraws()->latest('version')->first();
        $candidate = new TournamentScheduleBlock([...$data, 'competition_id' => $competition->id, 'tournament_draw_id' => $draw?->id]);
        if (!($data['force'] ?? false) && ($messages = $this->blockConflicts($candidate))) return response()->json(['message' => 'Blok waktu berbenturan.', 'conflicts' => $messages], 422);
        unset($data['force']);
        $competition->scheduleBlocks()->create([...$data, 'tournament_draw_id' => $draw?->id, 'created_by' => $request->user()->id]);
        return response()->json($this->payload($competition->fresh(), $draw), 201);
    }

    public function updateBlock(Request $request, TournamentScheduleBlock $block)
    {
        $this->authorizeCompetition($request, $block->competition);
        $data = $this->validateBlock($request);
        $candidate = $block->replicate()->fill($data); $candidate->id = $block->id;
        if (!($data['force'] ?? false) && ($messages = $this->blockConflicts($candidate))) return response()->json(['message' => 'Blok waktu berbenturan.', 'conflicts' => $messages], 422);
        unset($data['force']); $block->update($data);
        return $this->payload($block->competition->fresh(), $block->tournamentDraw);
    }

    public function destroyBlock(Request $request, TournamentScheduleBlock $block)
    {
        $this->authorizeCompetition($request, $block->competition); $competition = $block->competition; $draw = $block->tournamentDraw; $block->delete();
        return $this->payload($competition->fresh(), $draw);
    }

    private function validateBlock(Request $request): array
    {
        return $request->validate(['title' => 'required|string|max:120', 'venue' => 'required|string|max:160', 'starts_at' => 'required|date', 'duration_minutes' => 'required|integer|min:5|max:720', 'notes' => 'nullable|string|max:1000', 'force' => 'sometimes|boolean']);
    }

    private function interval($item, string $startField = 'scheduled_at'): ?array
    {
        $start = $item->{$startField}; if (!$start) return null;
        $start = $start instanceof Carbon ? $start->copy() : Carbon::parse($start);
        return [$start, $start->copy()->addMinutes((int)($item->duration_minutes ?: 60))];
    }

    private function overlaps(array $a, array $b): bool { return $a[0]->lt($b[1]) && $b[0]->lt($a[1]); }

    private function candidateConflicts(TournamentMatch $match, array $data): array
    {
        $candidate = $match->replicate()->fill($data); $candidate->id = $match->id;
        $interval = $this->interval($candidate); $messages = [];
        foreach ($match->tournamentDraw->matches()->whereKeyNot($match->id)->get() as $other) {
            $otherInterval = $this->interval($other); if (!$otherInterval || !$this->overlaps($interval, $otherInterval)) continue;
            if ($candidate->venue === $other->venue) $messages[] = "Lapangan {$candidate->venue} sudah dipakai Match {$other->match_number}.";
            $shared = array_filter(array_intersect([$candidate->participant_a_id,$candidate->participant_b_id], [$other->participant_a_id,$other->participant_b_id]));
            if ($shared) $messages[] = "Peserta yang sama juga bermain di Match {$other->match_number}.";
        }
        foreach ($match->tournamentDraw->competition->scheduleBlocks as $block) {
            $blockInterval = $this->interval($block, 'starts_at');
            if ($candidate->venue === $block->venue && $blockInterval && $this->overlaps($interval, $blockInterval)) $messages[] = "Lapangan {$candidate->venue} diblokir untuk {$block->title}.";
        }
        return array_values(array_unique($messages));
    }

    private function blockConflicts(TournamentScheduleBlock $block): array
    {
        $interval = $this->interval($block, 'starts_at'); $messages = [];
        $draw = $block->tournamentDraw ?: $block->competition->tournamentDraws()->latest('version')->first();
        foreach ($draw?->matches ?? [] as $match) if ($match->venue === $block->venue && ($other = $this->interval($match)) && $this->overlaps($interval, $other)) $messages[] = "Berbenturan dengan Match {$match->match_number}.";
        foreach ($block->competition->scheduleBlocks()->whereKeyNot($block->id ?: 0)->get() as $other) if ($other->venue === $block->venue && $this->overlaps($interval, $this->interval($other, 'starts_at'))) $messages[] = "Berbenturan dengan {$other->title}.";
        return array_values(array_unique($messages));
    }

    private function allConflicts($matches, $blocks): array
    {
        $items = [];
        foreach ($matches as $match) if ($this->interval($match)) $items[] = ['kind'=>'match','item'=>$match,'interval'=>$this->interval($match)];
        foreach ($blocks as $block) $items[] = ['kind'=>'block','item'=>$block,'interval'=>$this->interval($block, 'starts_at')];
        $conflicts = [];
        for ($i=0; $i<count($items); $i++) for ($j=$i+1; $j<count($items); $j++) {
            $a=$items[$i]; $b=$items[$j]; if (!$this->overlaps($a['interval'],$b['interval'])) continue;
            $sameVenue=$a['item']->venue===$b['item']->venue;
            $shared=false;
            if ($a['kind']==='match'&&$b['kind']==='match') $shared=(bool)array_filter(array_intersect([$a['item']->participant_a_id,$a['item']->participant_b_id],[$b['item']->participant_a_id,$b['item']->participant_b_id]));
            if ($sameVenue||$shared) $conflicts[]=['left_type'=>$a['kind'],'left_id'=>$a['item']->id,'right_type'=>$b['kind'],'right_id'=>$b['item']->id,'message'=>$sameVenue?'Benturan lapangan '.$a['item']->venue:'Peserta bermain pada dua pertandingan bersamaan'];
        }
        return $conflicts;
    }

    private function notifyParticipants(Request $request, Competition $competition, TournamentMatch $match): void
    {
        $when = $match->scheduled_at ? $match->scheduled_at->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB' : 'belum dijadwalkan';
        CompetitionNotification::create(['competition_id'=>$competition->id,'author_id'=>$request->user()->id,'title'=>"Pembaruan Jadwal Match {$match->match_number}",'message'=>"{$match->round_label}: {$when}, {$match->venue}. Status: {$match->status}.",'published_at'=>now()]);
    }

    public function publicView(string $slug)
    {
        $competition = Competition::where('event_edition_id', EventEdition::resolveCurrent(true)->id)->where('slug', $slug)->firstOrFail();
        $draw = $competition->tournamentDraws()->where('status','locked')->latest('version')->first();
        return $this->payload($competition, $draw);
    }
}
