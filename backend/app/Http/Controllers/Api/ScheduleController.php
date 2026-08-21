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
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    private const STATUSES = ['unscheduled','upcoming','check_in','ongoing','delayed','completed','walkover','cancelled','bye'];
    private const TIMEZONE = 'Asia/Jakarta';

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
            'timezone' => self::TIMEZONE,
            'timezone_label' => 'WIB',
            'utc_offset' => '+07:00',
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

    public function generate(Request $request, Competition $competition)
    {
        $this->authorizeCompetition($request, $competition);
        $data = $request->validate([
            'start_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'duration_minutes' => 'required|integer|min:5|max:720',
            'gap_minutes' => 'nullable|integer|min:0|max:240',
            'max_days' => 'nullable|integer|min:1|max:31',
            'venues' => 'required|array|min:1|max:20',
            'venues.*' => 'required|string|max:160|distinct',
            'replace_existing' => 'sometimes|boolean',
            'notify' => 'sometimes|boolean',
        ]);

        $configuredVenues = collect($this->venues($competition));
        $venues = collect($data['venues'])->values();
        abort_if($venues->diff($configuredVenues)->isNotEmpty(), 422, 'Pilihan lapangan tidak tersedia pada lomba ini.');

        $draw = $competition->tournamentDraws()->latest('version')->first();
        abort_unless($draw, 422, 'Buat drawing dan bagan terlebih dahulu sebelum membuat jadwal otomatis.');

        $matches = $draw->matches()->orderBy('round_number')->orderBy('match_number')->get();
        $replaceExisting = (bool)($data['replace_existing'] ?? false);
        $immutableStatuses = ['completed', 'walkover', 'bye', 'cancelled', 'check_in', 'ongoing'];
        $targets = $matches->filter(function (TournamentMatch $match) use ($replaceExisting, $immutableStatuses) {
            if (!$match->participant_a_id || !$match->participant_b_id || in_array($match->status, $immutableStatuses, true)) return false;
            return $replaceExisting || !$match->scheduled_at || $match->status === 'unscheduled';
        })->values();

        abort_if($targets->isEmpty(), 422, 'Tidak ada pertandingan siap yang perlu dijadwalkan. Pertandingan dengan peserta yang belum diketahui akan menunggu hasil babak sebelumnya.');

        $targetIds = $targets->pluck('id');
        $fixedMatches = $matches->reject(fn (TournamentMatch $match) => $targetIds->contains($match->id) || !$match->scheduled_at)->values();
        $blocks = $competition->scheduleBlocks()->where(function ($query) use ($draw) {
            $query->whereNull('tournament_draw_id')->orWhere('tournament_draw_id', $draw->id);
        })->get();

        $duration = (int)$data['duration_minutes'];
        $gap = (int)($data['gap_minutes'] ?? 0);
        $maxDays = (int)($data['max_days'] ?? 1);
        $firstDay = Carbon::parse($data['start_date'], self::TIMEZONE)->startOfDay();
        $slots = collect();
        for ($dayOffset = 0; $dayOffset < $maxDays; $dayOffset++) {
            $date = $firstDay->copy()->addDays($dayOffset)->format('Y-m-d');
            $cursor = Carbon::parse("{$date} {$data['start_time']}", self::TIMEZONE);
            $dayEnd = Carbon::parse("{$date} {$data['end_time']}", self::TIMEZONE);
            while ($cursor->copy()->addMinutes($duration)->lte($dayEnd)) {
                $slots->push($cursor->copy()->utc());
                $cursor->addMinutes($duration + $gap);
            }
        }
        abort_if($slots->isEmpty(), 422, 'Rentang waktu harian terlalu pendek untuk durasi pertandingan yang dipilih.');

        $planned = collect();
        foreach ($targets as $match) {
            $placement = null;
            foreach ($slots as $start) {
                $end = $start->copy()->addMinutes($duration);
                foreach ($venues as $venue) {
                    if ($this->automaticSlotAvailable($match, $start, $end, $venue, $fixedMatches, $blocks, $planned, $gap)) {
                        $placement = ['match' => $match, 'start' => $start->copy(), 'end' => $end, 'venue' => $venue];
                        break 2;
                    }
                }
            }
            abort_unless($placement, 422, 'Kapasitas jadwal tidak cukup. Tambah jumlah hari/lapangan, perpanjang jam operasional, atau kurangi durasi dan jeda pertandingan.');
            $planned->push($placement);
        }

        DB::transaction(function () use ($planned, $duration) {
            foreach ($planned as $item) {
                $item['match']->update([
                    'scheduled_at' => $item['start'],
                    'venue' => $item['venue'],
                    'duration_minutes' => $duration,
                    'status' => 'upcoming',
                ]);
            }
        });

        $firstStart = $planned->sortBy(fn ($item) => $item['start']->timestamp)->first()['start'];
        $lastEnd = $planned->sortByDesc(fn ($item) => $item['end']->timestamp)->first()['end'];

        if ($request->boolean('notify')) {
            CompetitionNotification::create([
                'competition_id' => $competition->id,
                'author_id' => $request->user()->id,
                'title' => 'Jadwal Pertandingan Telah Dibuat',
                'message' => $planned->count().' pertandingan dijadwalkan otomatis pada '.$firstStart->copy()->timezone(self::TIMEZONE)->format('d M Y H:i').' WIB sampai '.$lastEnd->copy()->timezone(self::TIMEZONE)->format('d M Y H:i').' WIB.',
                'published_at' => now(),
            ]);
        }

        return [
            ...$this->payload($competition->fresh(), $draw->fresh()),
            'automation' => [
                'scheduled_count' => $planned->count(),
                'waiting_count' => $matches->filter(fn ($match) => !$match->participant_a_id || !$match->participant_b_id)->count(),
                'start_at' => $firstStart->toIso8601String(),
                'end_at' => $lastEnd->toIso8601String(),
            ],
        ];
    }

    private function automaticSlotAvailable(TournamentMatch $candidate, Carbon $start, Carbon $end, string $venue, $fixedMatches, $blocks, $planned, int $gap): bool
    {
        $candidateParticipants = array_filter([$candidate->participant_a_id, $candidate->participant_b_id]);
        foreach ($fixedMatches as $other) {
            $otherStart = $other->scheduled_at;
            $otherEnd = $otherStart->copy()->addMinutes((int)($other->duration_minutes ?: 60));
            if ($venue === $other->venue && $this->overlaps([$start, $end->copy()->addMinutes($gap)], [$otherStart, $otherEnd->copy()->addMinutes($gap)])) return false;
            $shared = array_intersect($candidateParticipants, array_filter([$other->participant_a_id, $other->participant_b_id]));
            if ($shared && $this->overlaps([$start, $end->copy()->addMinutes($gap)], [$otherStart, $otherEnd->copy()->addMinutes($gap)])) return false;
        }
        foreach ($blocks as $block) {
            $blockInterval = $this->interval($block, 'starts_at');
            if ($venue === $block->venue && $blockInterval && $this->overlaps([$start, $end], $blockInterval)) return false;
        }
        foreach ($planned as $other) {
            if ($venue === $other['venue'] && $this->overlaps([$start, $end->copy()->addMinutes($gap)], [$other['start'], $other['end']->copy()->addMinutes($gap)])) return false;
            $shared = array_intersect($candidateParticipants, array_filter([$other['match']->participant_a_id, $other['match']->participant_b_id]));
            if ($shared && $this->overlaps([$start, $end->copy()->addMinutes($gap)], [$other['start'], $other['end']->copy()->addMinutes($gap)])) return false;
        }
        return true;
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

        // Input datetime-local dari panel selalu dibaca sebagai waktu Jakarta,
        // sedangkan database tetap menyimpan waktu UTC agar tidak ambigu.
        if (!empty($data['scheduled_at'])) $data['scheduled_at'] = $this->jakartaToUtc($data['scheduled_at']);

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
        $data = $request->validate(['title' => 'required|string|max:120', 'venue' => 'required|string|max:160', 'starts_at' => 'required|date', 'duration_minutes' => 'required|integer|min:5|max:720', 'notes' => 'nullable|string|max:1000', 'force' => 'sometimes|boolean']);
        $data['starts_at'] = $this->jakartaToUtc($data['starts_at']);
        return $data;
    }

    private function jakartaToUtc(string $value): Carbon
    {
        return Carbon::parse($value, self::TIMEZONE)->utc();
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
