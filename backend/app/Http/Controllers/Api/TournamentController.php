<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionSession;
use App\Models\Registration;
use App\Models\TournamentDraw;
use App\Models\TournamentMatch;
use App\Models\EventEdition;
use App\Models\CompetitionResult;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TournamentController extends Controller
{
    private function competitions(Request $request)
    {
        return $request->user()->manageableCompetitionsQuery(EventEdition::resolveCurrent()->id);
    }

    private function authorizeCompetition(Request $request, Competition $competition): void
    {
        abort_unless($this->competitions($request)->whereKey($competition->id)->exists(),403);
    }

    private function accessibleSessions(Request $request, Competition $competition)
    {
        $query = $competition->sessions()->where('is_active', true);
        $user = $request->user();
        if ($user->managesAllLocations()) return $query;

        return $query->whereIn(
            'competition_sessions.id',
            $user->manageableCompetitionSessionsQuery(EventEdition::resolveCurrent()->id)
                ->where('competition_sessions.competition_id', $competition->id)
                ->select('competition_sessions.id')
        );
    }

    private function scopeOptions(Request $request): Collection
    {
        return $this->competitions($request)->orderBy('title')->get()->flatMap(function (Competition $competition) use ($request) {
            $hasSessions = $competition->sessions()->exists();
            $sessions = $this->accessibleSessions($request, $competition)->with('venueRecord:id,slug,city,name')->get();
            if (! $hasSessions) return [[
                'competition_id'=>$competition->id, 'session_id'=>null, 'competition_title'=>$competition->title,
                'city'=>null, 'venue'=>null, 'label'=>$competition->title,
            ]];
            return $sessions->map(fn (CompetitionSession $session) => [
                'competition_id'=>$competition->id, 'session_id'=>$session->id, 'competition_title'=>$competition->title,
                'city'=>$session->city, 'venue'=>$session->venue, 'venue_slug'=>$session->venueRecord?->slug,
                'label'=>$competition->title.' · '.$session->city,
            ]);
        })->values();
    }

    private function selectedScope(Request $request): ?array
    {
        $scopes = $this->scopeOptions($request);
        if ($request->filled('session_id')) return $scopes->first(fn ($scope) => (int)$scope['session_id'] === $request->integer('session_id'));
        if ($request->filled('competition_id')) return $scopes->first(fn ($scope) => (int)$scope['competition_id'] === $request->integer('competition_id'));
        return $scopes->first();
    }

    private function selectedSession(Request $request, Competition $competition): ?CompetitionSession
    {
        if (! $competition->sessions()->exists()) return null;
        abort_unless($request->filled('competition_session_id'), 422, 'Pilih kota pelaksanaan terlebih dahulu.');
        return $this->accessibleSessions($request, $competition)->whereKey($request->integer('competition_session_id'))->firstOrFail();
    }

    private function authorizeDraw(Request $request, TournamentDraw $draw): void
    {
        $this->authorizeCompetition($request, $draw->competition);
        if ($draw->competition_session_id) {
            abort_unless($this->accessibleSessions($request, $draw->competition)->whereKey($draw->competition_session_id)->exists(), 403);
        }
    }

    private function eligibleParticipants(Competition $competition, ?CompetitionSession $session = null)
    {
        return Registration::where('competition_id', $competition->id)
            ->when($session, fn ($query) => $query->where('competition_session_id', $session->id))
            ->where('status', 'approved')
            ->when($competition->participation_type === 'team', fn ($query) => $query
                ->whereNotNull('team_completed_at')
                ->whereNotNull('reviewed_by')
                ->whereNotNull('reviewed_at')
                ->has('members', '=', $competition->team_size));
    }

    private function forceMajeureCandidates(Competition $competition, ?CompetitionSession $session = null): Collection
    {
        $eligibleIds = $this->eligibleParticipants($competition, $session)->pluck('id');

        return Registration::where('competition_id', $competition->id)
            ->when($session, fn ($query) => $query->where('competition_session_id', $session->id))
            ->where('status', '!=', 'rejected')
            ->whereNotIn('id', $eligibleIds)
            ->withCount('members')
            ->orderByRaw('COALESCE(team_name, full_name)')
            ->get([
                'id', 'ticket_code', 'full_name', 'team_name', 'school_name', 'status',
                'team_completed_at', 'reviewed_by', 'reviewed_at',
            ])
            ->map(function (Registration $registration) use ($competition) {
                $issues = [];
                if ($registration->status === 'pending') $issues[] = 'Menunggu keputusan verifikasi';
                if ($registration->status === 'revision') $issues[] = 'Masih membutuhkan revisi';
                if ($competition->participation_type === 'team') {
                    if (! $registration->team_completed_at) $issues[] = 'Data tim belum ditandai lengkap';
                    if ((int) $registration->members_count !== (int) $competition->team_size) {
                        $issues[] = "Pemain tersimpan {$registration->members_count}/{$competition->team_size}";
                    }
                    if (! $registration->reviewed_by || ! $registration->reviewed_at) $issues[] = 'Belum diperiksa petugas';
                }
                $registration->setAttribute('force_majeure_issues', $issues ?: ['Belum memenuhi syarat drawing']);
                return $registration;
            });
    }

    private function drawPayload(TournamentDraw $draw): TournamentDraw
    {
        $draw->load([
            'operator:id,name','competition:id,title,slug',
            'competitionSession:id,competition_id,venue_id,city,venue,competition_start_date,competition_end_date,schedule_venues',
            'competitionSession.venueRecord:id,slug,city,name',
            'entries.registration:id,full_name,team_name,school_name,status',
            'matches.participantA:id,full_name,team_name,school_name,status',
            'matches.participantB:id,full_name,team_name,school_name,status',
            'matches.winner:id,full_name,team_name,school_name,status',
        ]);

        $draw->setAttribute('group_standings', $this->groupStandings($draw));
        return $draw;
    }

    private function groupStandings(TournamentDraw $draw): array
    {
        if (! in_array($draw->format, ['round_robin', 'round_robin_full', 'groups_knockout'], true)) return [];

        $draw->loadMissing([
            'entries.registration:id,full_name,team_name,school_name,status',
            'matches.participantA:id,full_name,team_name,school_name,status',
            'matches.participantB:id,full_name,team_name,school_name,status',
        ]);
        $matchesByGroup = $draw->matches->where('stage', 'group')->groupBy(fn ($match) => $match->group_name ?: $match->round_label);

        return $draw->entries->whereNotNull('registration_id')->groupBy(fn ($entry) => $entry->group_name ?: 'Liga')
            ->map(function ($entries, $groupName) use ($draw, $matchesByGroup) {
                $rows = [];
                foreach ($entries as $entry) {
                    $participant = $entry->registration;
                    $rows[$entry->registration_id] = [
                        'registration_id' => $entry->registration_id,
                        'participant' => $participant?->only(['id', 'full_name', 'team_name', 'school_name']),
                        'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
                        'goals_for' => 0, 'goals_against' => 0, 'goal_difference' => 0, 'points' => 0,
                    ];
                }

                $groupMatches = $matchesByGroup->get($groupName, collect());
                $completedMatches = $groupMatches->where('status', 'completed');
                foreach ($completedMatches as $match) {
                    $a = $match->participant_a_id;
                    $b = $match->participant_b_id;
                    if (! isset($rows[$a], $rows[$b])) continue;
                    $scoreA = (float) $match->score_a;
                    $scoreB = (float) $match->score_b;
                    $rows[$a]['played']++; $rows[$b]['played']++;
                    $rows[$a]['goals_for'] += $scoreA; $rows[$a]['goals_against'] += $scoreB;
                    $rows[$b]['goals_for'] += $scoreB; $rows[$b]['goals_against'] += $scoreA;
                    if ($scoreA === $scoreB) {
                        $rows[$a]['drawn']++; $rows[$b]['drawn']++;
                        $rows[$a]['points']++; $rows[$b]['points']++;
                    } elseif ($scoreA > $scoreB) {
                        $rows[$a]['won']++; $rows[$b]['lost']++; $rows[$a]['points'] += 3;
                    } else {
                        $rows[$b]['won']++; $rows[$a]['lost']++; $rows[$b]['points'] += 3;
                    }
                }

                foreach ($rows as &$row) $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];
                unset($row);
                $rows = array_values($rows);
                usort($rows, fn ($a, $b) =>
                    ($b['points'] <=> $a['points'])
                    ?: ($b['goal_difference'] <=> $a['goal_difference'])
                    ?: ($b['goals_for'] <=> $a['goals_for'])
                    ?: ($b['won'] <=> $a['won'])
                    ?: strcmp(mb_strtolower($a['participant']['team_name'] ?: $a['participant']['full_name']), mb_strtolower($b['participant']['team_name'] ?: $b['participant']['full_name']))
                );
                $completed = $groupMatches->isNotEmpty() && $completedMatches->count() === $groupMatches->count();
                foreach ($rows as $index => &$row) {
                    $row['position'] = $index + 1;
                    $row['qualified'] = $draw->format === 'groups_knockout' && $completed && $index < 2;
                }
                unset($row);

                return [
                    'name' => $groupName,
                    'played_matches' => $completedMatches->count(),
                    'total_matches' => $groupMatches->count(),
                    'completed' => $completed,
                    'rows' => $rows,
                ];
            })->values()->all();
    }

    public function manage(Request $request)
    {
        $scopes=$this->scopeOptions($request);
        $scope=$this->selectedScope($request);
        $options=$this->competitions($request)->orderBy('title')->get(['id','title','slug']);
        if(!$scope)return ['scopes'=>$scopes,'competitions'=>$options,'competition'=>null,'session'=>null,'participants'=>[],'draw'=>null,'history'=>[],'can_unlock'=>$request->user()->role==='super_admin'];
        $competition=$this->competitions($request)->whereKey($scope['competition_id'])->firstOrFail();
        $session=$scope['session_id']?$this->accessibleSessions($request,$competition)->whereKey($scope['session_id'])->firstOrFail():null;
        $participants=$this->eligibleParticipants($competition,$session)
            ->orderBy('full_name')->get(['id','ticket_code','full_name','team_name','school_name']);
        $forceMajeureCandidates=$this->forceMajeureCandidates($competition,$session);
        $draw=$competition->tournamentDraws()->where('competition_session_id',$session?->id)->latest('version')->first();
        return ['scopes'=>$scopes,'competitions'=>$options,'competition'=>$competition->only(['id','title','slug']),
            'session'=>$session?->only(['id','competition_id','venue_id','city','venue','competition_start_date','competition_end_date','schedule_venues']),
            'participants'=>$participants,'force_majeure_candidates'=>$forceMajeureCandidates,
            'drawing_readiness'=>[
                'verified'=>$participants->count(),
                'force_majeure_candidates'=>$forceMajeureCandidates->count(),
                'rejected'=>Registration::where('competition_id',$competition->id)->where('status','rejected')->count(),
            ],
            'can_unlock'=>$request->user()->role==='super_admin',
            'draw'=>$draw?$this->drawPayload($draw):null,
            'history'=>$competition->tournamentDraws()->where('competition_session_id',$session?->id)->with('operator:id,name')->latest('version')->get(['id','competition_id','competition_session_id','operator_id','version','mode','format','status','drawn_at','locked_at'])];
    }

    public function start(Request $request, Competition $competition)
    {
        $this->authorizeCompetition($request,$competition);
        $data=$request->validate([
            'mode'=>'required|in:random,seeded,manual',
            'format'=>'required|in:single_elimination,double_elimination,round_robin,round_robin_full,groups_knockout',
            'seeded_ids'=>'nullable|array','seeded_ids.*'=>'integer',
            'host_ids'=>'nullable|array','host_ids.*'=>'integer',
            'manual_order'=>'nullable|array','manual_order.*'=>'integer',
            'manual_slots'=>'nullable|array|max:64','manual_slots.*'=>'nullable|integer',
            'manual_groups'=>'nullable|array|max:16','manual_groups.*'=>'array','manual_groups.*.*'=>'integer',
            'avoid_same_school'=>'boolean','separate_seeds'=>'boolean','host_policy'=>'nullable|in:random,first,last',
            'group_count'=>'nullable|integer|min:2|max:16','third_place'=>'boolean',
            'force_majeure_ids'=>'nullable|array|max:64','force_majeure_ids.*'=>'distinct|integer',
            'force_majeure_reason'=>'nullable|string|max:1000',
            'competition_session_id'=>'nullable|integer|exists:competition_sessions,id',
        ]);
        $session=$this->selectedSession($request,$competition);
        $latest=$competition->tournamentDraws()->where('competition_session_id',$session?->id)->latest('version')->first();
        abort_if($latest?->status==='locked',422,'Drawing telah dikunci dan tidak dapat diulang.');
        $forceMajeureIds=collect($data['force_majeure_ids']??[])->map(fn($id)=>(int)$id)->unique()->values();
        $forceMajeureCandidates=$this->forceMajeureCandidates($competition,$session)->keyBy('id');
        abort_if($forceMajeureIds->diff($forceMajeureCandidates->keys())->isNotEmpty(),422,'Pilihan force majeure tidak valid atau tim sudah ditolak.');
        abort_if($forceMajeureIds->isNotEmpty()&&mb_strlen(trim((string)($data['force_majeure_reason']??'')))<10,422,'Alasan force majeure wajib diisi minimal 10 karakter.');
        $participants=$this->eligibleParticipants($competition,$session)->get(['id','full_name','team_name','school_name'])
            ->concat($forceMajeureIds->map(fn($id)=>$forceMajeureCandidates[$id]))->unique('id')->values();
        abort_if($participants->count()<2,422,'Minimal dua peserta terverifikasi atau peserta force majeure diperlukan untuk drawing.');
        abort_if($participants->count()>64,422,'Maksimal 64 peserta dalam satu drawing.');
        $ids=$participants->pluck('id');
        foreach(['seeded_ids','host_ids','manual_order'] as $key)if(collect($data[$key]??[])->diff($ids)->isNotEmpty())return response()->json(['message'=>'Daftar peserta drawing tidak valid.'],422);
        if($data['format']==='groups_knockout'){
            $groupCount=(int)($data['group_count']??2);
            abort_if($groupCount>$participants->count()/2,422,'Setiap grup harus berisi minimal dua peserta. Kurangi jumlah grup.');
        }
        if($data['mode']==='manual'){
            if(in_array($data['format'],['single_elimination','double_elimination'],true)){
                $manualSlots=collect($data['manual_slots']??[]);
                $placed=$manualSlots->filter(fn($id)=>$id!==null)->map(fn($id)=>(int)$id)->values();
                abort_if($manualSlots->count()!==$this->bracketSize($participants->count()),422,'Jumlah slot manual harus sesuai ukuran bracket.');
                abort_if($placed->sort()->values()->all()!==$ids->map(fn($id)=>(int)$id)->sort()->values()->all(),422,'Slot manual harus memuat setiap peserta tepat satu kali; slot lainnya boleh BYE.');
            }elseif($data['format']==='groups_knockout'){
                $manualGroups=collect($data['manual_groups']??[]);
                $groupCount=(int)($data['group_count']??2);
                $placed=$manualGroups->flatten()->map(fn($id)=>(int)$id)->values();
                abort_if($manualGroups->count()!==$groupCount,422,'Jumlah kelompok manual harus sama dengan jumlah grup.');
                abort_if($manualGroups->contains(fn($group)=>count($group)<2),422,'Setiap grup manual harus berisi minimal dua peserta.');
                abort_if($placed->sort()->values()->all()!==$ids->map(fn($id)=>(int)$id)->sort()->values()->all(),422,'Kelompok manual harus memuat setiap peserta tepat satu kali.');
            }elseif(collect($data['manual_order']??[])->map(fn($id)=>(int)$id)->sort()->values()->all()!==$ids->map(fn($id)=>(int)$id)->sort()->values()->all()){
                return response()->json(['message'=>'Urutan manual harus memuat seluruh peserta tepat satu kali.'],422);
            }
        }

        $draw=DB::transaction(function()use($request,$competition,$session,$data,$participants,$latest,$forceMajeureIds,$forceMajeureCandidates){
            $settings=collect($data)->except(['mode','format','force_majeure_ids','force_majeure_reason','competition_session_id'])->all();
            if($forceMajeureIds->isNotEmpty())$settings['force_majeure']=[
                'registration_ids'=>$forceMajeureIds->all(),
                'reason'=>trim($data['force_majeure_reason']),
                'approved_by'=>['id'=>$request->user()->id,'name'=>$request->user()->name],
                'approved_at'=>now()->toIso8601String(),
                'teams'=>$forceMajeureIds->map(function($id)use($forceMajeureCandidates){$candidate=$forceMajeureCandidates[$id];return [
                    'registration_id'=>$candidate->id,
                    'ticket_code'=>$candidate->ticket_code,
                    'name'=>$candidate->team_name?:$candidate->full_name,
                    'status'=>$candidate->status,
                    'issues'=>$candidate->force_majeure_issues,
                ];})->all(),
            ];
            $draw=$competition->tournamentDraws()->create(['competition_session_id'=>$session?->id,'operator_id'=>$request->user()->id,'version'=>($latest?->version??0)+1,'mode'=>$data['mode'],'format'=>$data['format'],'settings'=>$settings,'drawn_at'=>now()]);
            $ordered=$this->orderParticipants($participants,$data);
            if(in_array($data['format'],['round_robin','round_robin_full','groups_knockout'],true))$this->createGroupDraw($draw,$ordered,$data);
            else $this->createBracketDraw($draw,$ordered,$data);
            return $draw;
        });
        return response()->json($this->drawPayload($draw),201);
    }

    private function orderParticipants(Collection $participants,array $data): Collection
    {
        $byId=$participants->keyBy('id');
        if($data['mode']==='manual'){
            $manualIds=isset($data['manual_slots'])?collect($data['manual_slots'])->filter(fn($id)=>$id!==null)
                :(isset($data['manual_groups'])?collect($data['manual_groups'])->flatten():collect($data['manual_order']??[]));
            $ordered=$manualIds->map(fn($id)=>$byId[$id]);
        }
        elseif($data['mode']==='seeded'){
            $seeds=collect($data['seeded_ids']??[])->unique()->filter(fn($id)=>$byId->has($id))->map(fn($id)=>$byId[$id]);
            $ordered=$seeds->concat($participants->whereNotIn('id',$seeds->pluck('id'))->shuffle());
        }else $ordered=$participants->shuffle();
        $hosts=collect($data['host_ids']??[]);
        if($data['mode']!=='manual'&&($data['host_policy']??'random')==='first')$ordered=$ordered->sortByDesc(fn($p)=>$hosts->contains($p->id))->values();
        if($data['mode']!=='manual'&&($data['host_policy']??'random')==='last')$ordered=$ordered->sortBy(fn($p)=>$hosts->contains($p->id))->values();
        return $ordered->values();
    }

    private function bracketSize(int $count): int
    {
        $size=2;while($size<$count)$size*=2;return min($size,64);
    }

    private function createBracketDraw(TournamentDraw $draw,Collection $participants,array $settings): void
    {
        $size=$this->bracketSize($participants->count());
        if($draw->mode==='manual'&&isset($settings['manual_slots'])){
            $byId=$participants->keyBy('id');
            $slots=collect($settings['manual_slots'])->map(fn($id)=>$id===null?null:$byId[(int)$id])->all();
        }else{
            $byeCount=$size-$participants->count();$byeSlots=[];
            for($i=0;$i<$byeCount;$i++){$slot=(int)floor($i*$size/max($byeCount,1));if($slot%2)$slot--;while(in_array($slot,$byeSlots,true))$slot=($slot+2)%$size;$byeSlots[]=$slot;}
            $slots=array_fill(0,$size,null);$remaining=$participants->values();
            $seedCount=min(collect($settings['seeded_ids']??[])->count(),$remaining->count());
            if(($settings['separate_seeds']??false)&&$seedCount>1){
                for($i=0;$i<$seedCount;$i++){$candidate=(int)floor($i*$size/$seedCount);while(in_array($candidate,$byeSlots,true)||$slots[$candidate])$candidate=($candidate+1)%$size;$slots[$candidate]=$remaining->shift();}
            }
            foreach(range(0,$size-1) as $slot)if(!in_array($slot,$byeSlots,true)&&!$slots[$slot])$slots[$slot]=$remaining->shift();
            if(($settings['avoid_same_school']??false))$this->separateSameSchool($slots);
        }
        $seedIds=collect($settings['seeded_ids']??[])->values();
        foreach($slots as $index=>$participant)$draw->entries()->create(['registration_id'=>$participant?->id,'slot_number'=>$index+1,'seed_number'=>$participant?($seedIds->search($participant->id)!==false?$seedIds->search($participant->id)+1:null):null,'is_bye'=>!$participant]);
        $this->createEliminationMatches($draw,$slots,$settings,$draw->format==='double_elimination');
    }

    private function separateSameSchool(array &$slots): void
    {
        for($i=0;$i<count($slots);$i+=2){if(!$slots[$i]||!$slots[$i+1]||$slots[$i]->school_name!==$slots[$i+1]->school_name)continue;
            for($j=$i+2;$j<count($slots);$j++)if($slots[$j]&&$slots[$j]->school_name!==$slots[$i]->school_name){[$slots[$i+1],$slots[$j]]=[$slots[$j],$slots[$i+1]];break;}}
    }

    private function roundLabel(int $round,int $total): string
    {
        $remaining=2**($total-$round+1);return match($remaining){2=>'Final',4=>'Semifinal',8=>'Perempat Final',default=>'Babak '.$round};
    }

    private function createEliminationMatches(TournamentDraw $draw,array $slots,array $settings,bool $double=false,string $stage='main',int $startNumber=1): array
    {
        $rounds=[];$total=(int)log(count($slots),2);$number=$startNumber;
        for($round=1;$round<=$total;$round++){
            $count=count($slots)/(2**$round);$current=[];
            for($i=0;$i<$count;$i++){
                $attrs=['stage'=>$stage==='main'&&$double?'winner':$stage,'round_number'=>$round,'round_label'=>$this->roundLabel($round,$total),'match_number'=>$number++];
                if($round===1){$attrs['participant_a_id']=$slots[$i*2]?->id;$attrs['participant_b_id']=$slots[$i*2+1]?->id;}
                else{$attrs+=['source_a_match_id'=>$rounds[$round-2][$i*2]->id,'source_a_outcome'=>'winner','source_b_match_id'=>$rounds[$round-2][$i*2+1]->id,'source_b_outcome'=>'winner'];}
                $current[]=$draw->matches()->create($attrs);
            }$rounds[]=$current;
        }
        if(($settings['third_place']??false)&&!$double&&$total>=2){$semis=$rounds[$total-2];$draw->matches()->create(['stage'=>'third_place','round_number'=>$total,'round_label'=>'Perebutan Juara Ketiga','match_number'=>$number++,'source_a_match_id'=>$semis[0]->id,'source_a_outcome'=>'loser','source_b_match_id'=>$semis[1]->id,'source_b_outcome'=>'loser']);}
        if($double)$this->createLoserBracket($draw,$rounds,$number,$total);
        foreach($draw->matches()->orderBy('match_number')->get() as $match)$this->resolveMatch($match);
        return $rounds;
    }

    private function createLoserBracket(TournamentDraw $draw,array $winnerRounds,int &$number,int $total): void
    {
        $first=$winnerRounds[0];$previous=[];
        for($i=0;$i<count($first);$i+=2)$previous[]=$draw->matches()->create(['stage'=>'loser','round_number'=>1,'round_label'=>'Loser Round 1','match_number'=>$number++,'source_a_match_id'=>$first[$i]->id,'source_a_outcome'=>'loser','source_b_match_id'=>$first[$i+1]->id,'source_b_outcome'=>'loser']);
        $loserRound=2;
        for($winnerRound=2;$winnerRound<=$total;$winnerRound++){
            $wb=$winnerRounds[$winnerRound-1];$cross=[];
            foreach($wb as $i=>$match)$cross[]=$draw->matches()->create(['stage'=>'loser','round_number'=>$loserRound,'round_label'=>'Loser Round '.$loserRound,'match_number'=>$number++,'source_a_match_id'=>$previous[$i]->id,'source_a_outcome'=>'winner','source_b_match_id'=>$match->id,'source_b_outcome'=>'loser']);
            $previous=$cross;$loserRound++;
            if($winnerRound<$total){$paired=[];for($i=0;$i<count($previous);$i+=2)$paired[]=$draw->matches()->create(['stage'=>'loser','round_number'=>$loserRound,'round_label'=>'Loser Round '.$loserRound,'match_number'=>$number++,'source_a_match_id'=>$previous[$i]->id,'source_a_outcome'=>'winner','source_b_match_id'=>$previous[$i+1]->id,'source_b_outcome'=>'winner']);$previous=$paired;$loserRound++;}
        }
        $draw->matches()->create(['stage'=>'grand_final','round_number'=>$total+1,'round_label'=>'Grand Final','match_number'=>$number++,'source_a_match_id'=>end($winnerRounds)[0]->id,'source_a_outcome'=>'winner','source_b_match_id'=>$previous[0]->id,'source_b_outcome'=>'winner']);
    }

    private function createGroupDraw(TournamentDraw $draw,Collection $participants,array $settings): void
    {
        $groupCount=$draw->format==='groups_knockout'?(int)($settings['group_count']??2):1;
        if($draw->mode==='manual'&&$draw->format==='groups_knockout'&&isset($settings['manual_groups'])){
            $byId=$participants->keyBy('id');
            $groups=collect($settings['manual_groups'])->map(fn($group)=>collect($group)->map(fn($id)=>$byId[(int)$id])->all())->all();
        }else{
            $groups=array_fill(0,$groupCount,[]);
            foreach($participants as $i=>$participant)$groups[$i%$groupCount][]=$participant;
        }
        $slot=1;$number=1;
        foreach($groups as $groupIndex=>$members){$name=$groupCount===1?'Liga':'Grup '.chr(65+$groupIndex);foreach($members as $participant)$draw->entries()->create(['registration_id'=>$participant->id,'slot_number'=>$slot++,'group_name'=>$name]);
            for($i=0;$i<count($members);$i++)for($j=$i+1;$j<count($members);$j++){$draw->matches()->create(['stage'=>'group','round_number'=>1,'round_label'=>$name,'group_name'=>$name,'match_number'=>$number++,'participant_a_id'=>$members[$i]->id,'participant_b_id'=>$members[$j]->id]);if($draw->format==='round_robin_full')$draw->matches()->create(['stage'=>'group','round_number'=>2,'round_label'=>$name.' Putaran 2','group_name'=>$name,'match_number'=>$number++,'participant_a_id'=>$members[$j]->id,'participant_b_id'=>$members[$i]->id]);}}
    }

    public function updateMatch(Request $request,TournamentMatch $match)
    {
        $this->authorizeDraw($request,$match->tournamentDraw);
        $data=$request->validate(['score_a'=>'nullable|numeric|min:0','score_b'=>'nullable|numeric|min:0','scheduled_at'=>'nullable|date','venue'=>'nullable|string|max:160','duration_minutes'=>'nullable|integer|min:5|max:720','status'=>'required|in:unscheduled,upcoming,check_in,ongoing,delayed,completed,walkover,cancelled,bye']);
        if(!empty($data['scheduled_at']))$data['scheduled_at']=Carbon::parse($data['scheduled_at'],'Asia/Jakarta')->utc();
        if($data['status']==='completed'){
            abort_unless($match->participant_a_id&&$match->participant_b_id,422,'Peserta pertandingan belum lengkap.');
            if((float)$data['score_a']===(float)$data['score_b']){
                abort_if($match->stage!=='group',422,'Skor pertandingan gugur tidak boleh seri.');
                $data['winner_id']=null;
            }else $data['winner_id']=(float)$data['score_a']>(float)$data['score_b']?$match->participant_a_id:$match->participant_b_id;
        }else $data['winner_id']=null;
        $match->update($data);
        $this->resolveDependents($match);
        if ($match->status === 'completed' && $match->winner_id && preg_match('/\bfinal\b/i', $match->round_label)) {
            $loserId = $match->winner_id === $match->participant_a_id ? $match->participant_b_id : $match->participant_a_id;
            CompetitionResult::updateOrCreate(
                ['competition_id'=>$match->tournamentDraw->competition_id, 'competition_session_id'=>$match->tournamentDraw->competition_session_id, 'rank'=>1, 'source'=>'tournament'],
                ['registration_id'=>$match->winner_id, 'title'=>'Juara 1', 'announced_at'=>now()]
            );
            if ($loserId) CompetitionResult::updateOrCreate(
                ['competition_id'=>$match->tournamentDraw->competition_id, 'competition_session_id'=>$match->tournamentDraw->competition_session_id, 'rank'=>2, 'source'=>'tournament'],
                ['registration_id'=>$loserId, 'title'=>'Juara 2', 'announced_at'=>now()]
            );
        }
        return $this->drawPayload($match->tournamentDraw->fresh());
    }

    public function resolveDependents(TournamentMatch $source): void
    {
        TournamentMatch::where('source_a_match_id',$source->id)->orWhere('source_b_match_id',$source->id)->get()->each(fn($match)=>$this->resolveMatch($match));
    }

    public function resolveMatch(TournamentMatch $match): void
    {
        foreach(['a','b'] as $slot){$sourceId=$match->{'source_'.$slot.'_match_id'};if(!$sourceId)continue;$source=TournamentMatch::find($sourceId);if(!in_array($source?->status,['completed','bye'],true))return;$outcome=$match->{'source_'.$slot.'_outcome'};$participant=$outcome==='winner'?$source->winner_id:($source->winner_id===$source->participant_a_id?$source->participant_b_id:$source->participant_a_id);$match->{'participant_'.$slot.'_id'}=$participant;}
        $match->save();
        $sourcesResolved=(!$match->source_a_match_id||in_array(TournamentMatch::find($match->source_a_match_id)?->status,['completed','bye'],true))&&(!$match->source_b_match_id||in_array(TournamentMatch::find($match->source_b_match_id)?->status,['completed','bye'],true));
        if($sourcesResolved&&(!$match->participant_a_id||!$match->participant_b_id)){$match->winner_id=$match->participant_a_id?:$match->participant_b_id;$match->status='bye';$match->save();$this->resolveDependents($match);}
    }

    public function lock(Request $request,TournamentDraw $draw)
    {
        $this->authorizeDraw($request,$draw);$draw->update(['status'=>'locked','locked_at'=>now()]);return $this->drawPayload($draw->fresh());
    }

    public function unlock(Request $request,TournamentDraw $draw)
    {
        abort_unless($request->user()->role === 'super_admin', 403, 'Hanya Super Admin yang dapat membuka kunci drawing.');
        $this->authorizeDraw($request, $draw);
        abort_unless($draw->status === 'locked', 422, 'Drawing belum dikunci.');

        $settings = $draw->settings ?? [];
        $settings['unlock_history'] = collect($settings['unlock_history'] ?? [])->push([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'unlocked_at' => now()->toIso8601String(),
        ])->values()->all();
        $draw->update(['status'=>'draft', 'locked_at'=>null, 'settings'=>$settings]);

        return $this->drawPayload($draw->fresh());
    }

    public function generateKnockout(Request $request,TournamentDraw $draw)
    {
        $this->authorizeDraw($request,$draw);
        abort_unless($draw->format==='groups_knockout',422,'Format drawing ini bukan grup dilanjutkan knockout.');
        abort_if($draw->matches()->where('stage','knockout')->exists(),422,'Babak knockout sudah dibuat.');
        $groupMatches=$draw->matches()->where('stage','group')->get();
        abort_if($groupMatches->isEmpty()||$groupMatches->contains(fn($m)=>$m->status!=='completed'),422,'Selesaikan seluruh pertandingan grup terlebih dahulu.');
        $qualifiers=collect($this->groupStandings($draw))->flatMap(fn($group)=>collect($group['rows'])->take(2)->pluck('registration_id'))->all();
        $participants=Registration::whereIn('id',$qualifiers)->get()->keyBy('id');$ordered=collect($qualifiers)->map(fn($id)=>$participants[$id]);$size=2;while($size<$ordered->count())$size*=2;$slots=array_pad($ordered->all(),$size,null);
        $this->createEliminationMatches($draw,$slots,['third_place'=>$draw->settings['third_place']??false],false,'knockout',(int)$draw->matches()->max('match_number')+1);
        return $this->drawPayload($draw->fresh());
    }

    public function publicView(Request $request, string $slug)
    {
        $competition=Competition::where('event_edition_id', EventEdition::resolveCurrent(true)->id)->where('slug',$slug)->firstOrFail();
        $sessions=$competition->sessions()->where('is_active',true)->with('venueRecord:id,slug,city,name')->get();
        $session=$request->filled('session_id')?$sessions->firstWhere('id',$request->integer('session_id'))
            :($request->filled('city')?$sessions->first(fn($item)=>$item->venueRecord?->slug===$request->string('city')->toString()):$sessions->first());
        if($sessions->isNotEmpty())abort_unless($session,404);
        $draw=$competition->tournamentDraws()->where('competition_session_id',$session?->id)->where('status','locked')->latest('version')->first();
        return ['competition'=>$competition->only(['id','title','slug']),
            'sessions'=>$sessions->map(fn($item)=>[...$item->only(['id','city','venue','competition_start_date','competition_end_date']),'venue_slug'=>$item->venueRecord?->slug])->values(),
            'session'=>$session?->only(['id','city','venue','competition_start_date','competition_end_date']),
            'draw'=>$draw?$this->drawPayload($draw):null];
    }
}
