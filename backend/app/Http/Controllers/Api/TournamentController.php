<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Registration;
use App\Models\TournamentDraw;
use App\Models\TournamentMatch;
use App\Models\EventEdition;
use App\Models\CompetitionResult;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    private function eligibleParticipants(Competition $competition)
    {
        return Registration::where('competition_id', $competition->id)
            ->where('status', 'approved')
            ->when($competition->participation_type === 'team', fn ($query) => $query
                ->whereNotNull('team_completed_at')
                ->whereNotNull('reviewed_by')
                ->whereNotNull('reviewed_at')
                ->has('members', '=', $competition->team_size));
    }

    private function forceMajeureCandidates(Competition $competition): Collection
    {
        $eligibleIds = $this->eligibleParticipants($competition)->pluck('id');

        return Registration::where('competition_id', $competition->id)
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
        return $draw->load([
            'operator:id,name','competition:id,title,slug',
            'entries.registration:id,full_name,team_name,school_name,status',
            'matches.participantA:id,full_name,team_name,school_name,status',
            'matches.participantB:id,full_name,team_name,school_name,status',
            'matches.winner:id,full_name,team_name,school_name,status',
        ]);
    }

    public function manage(Request $request)
    {
        $options=$this->competitions($request)->orderBy('title')->get(['id','title','slug']);
        $competition=$request->filled('competition_id')
            ? $this->competitions($request)->whereKey($request->integer('competition_id'))->firstOrFail()
            : $this->competitions($request)->orderBy('title')->first();
        if(!$competition)return ['competitions'=>$options,'competition'=>null,'participants'=>[],'draw'=>null,'history'=>[]];
        $participants=$this->eligibleParticipants($competition)
            ->orderBy('full_name')->get(['id','ticket_code','full_name','team_name','school_name']);
        $forceMajeureCandidates=$this->forceMajeureCandidates($competition);
        $draw=$competition->tournamentDraws()->latest('version')->first();
        return ['competitions'=>$options,'competition'=>$competition->only(['id','title','slug']),
            'participants'=>$participants,'force_majeure_candidates'=>$forceMajeureCandidates,
            'drawing_readiness'=>[
                'verified'=>$participants->count(),
                'force_majeure_candidates'=>$forceMajeureCandidates->count(),
                'rejected'=>Registration::where('competition_id',$competition->id)->where('status','rejected')->count(),
            ],
            'draw'=>$draw?$this->drawPayload($draw):null,
            'history'=>$competition->tournamentDraws()->with('operator:id,name')->latest('version')->get(['id','competition_id','operator_id','version','mode','format','status','drawn_at','locked_at'])];
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
        ]);
        $latest=$competition->tournamentDraws()->latest('version')->first();
        abort_if($latest?->status==='locked',422,'Drawing telah dikunci dan tidak dapat diulang.');
        $forceMajeureIds=collect($data['force_majeure_ids']??[])->map(fn($id)=>(int)$id)->unique()->values();
        $forceMajeureCandidates=$this->forceMajeureCandidates($competition)->keyBy('id');
        abort_if($forceMajeureIds->diff($forceMajeureCandidates->keys())->isNotEmpty(),422,'Pilihan force majeure tidak valid atau tim sudah ditolak.');
        abort_if($forceMajeureIds->isNotEmpty()&&mb_strlen(trim((string)($data['force_majeure_reason']??'')))<10,422,'Alasan force majeure wajib diisi minimal 10 karakter.');
        $participants=$this->eligibleParticipants($competition)->get(['id','full_name','team_name','school_name'])
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

        $draw=DB::transaction(function()use($request,$competition,$data,$participants,$latest,$forceMajeureIds,$forceMajeureCandidates){
            $settings=collect($data)->except(['mode','format','force_majeure_ids','force_majeure_reason'])->all();
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
            $draw=$competition->tournamentDraws()->create(['operator_id'=>$request->user()->id,'version'=>($latest?->version??0)+1,'mode'=>$data['mode'],'format'=>$data['format'],'settings'=>$settings,'drawn_at'=>now()]);
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
        $this->authorizeCompetition($request,$match->tournamentDraw->competition);
        $data=$request->validate(['score_a'=>'nullable|numeric|min:0','score_b'=>'nullable|numeric|min:0','scheduled_at'=>'nullable|date','venue'=>'nullable|string|max:160','duration_minutes'=>'nullable|integer|min:5|max:720','status'=>'required|in:unscheduled,upcoming,check_in,ongoing,delayed,completed,walkover,cancelled,bye']);
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
                ['competition_id'=>$match->tournamentDraw->competition_id, 'competition_session_id'=>null, 'rank'=>1, 'source'=>'tournament'],
                ['registration_id'=>$match->winner_id, 'title'=>'Juara 1', 'announced_at'=>now()]
            );
            if ($loserId) CompetitionResult::updateOrCreate(
                ['competition_id'=>$match->tournamentDraw->competition_id, 'competition_session_id'=>null, 'rank'=>2, 'source'=>'tournament'],
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
        $this->authorizeCompetition($request,$draw->competition);$draw->update(['status'=>'locked','locked_at'=>now()]);return $this->drawPayload($draw->fresh());
    }

    public function generateKnockout(Request $request,TournamentDraw $draw)
    {
        $this->authorizeCompetition($request,$draw->competition);
        abort_unless($draw->format==='groups_knockout',422,'Format drawing ini bukan grup dilanjutkan knockout.');
        abort_if($draw->matches()->where('stage','knockout')->exists(),422,'Babak knockout sudah dibuat.');
        $groupMatches=$draw->matches()->where('stage','group')->get();
        abort_if($groupMatches->isEmpty()||$groupMatches->contains(fn($m)=>$m->status!=='completed'),422,'Selesaikan seluruh pertandingan grup terlebih dahulu.');
        $qualifiers=[];
        foreach($groupMatches->groupBy('group_name') as $matches){
            $table=[];
            foreach($matches as $match){foreach([$match->participant_a_id,$match->participant_b_id] as $id)$table[$id]??=['id'=>$id,'points'=>0,'difference'=>0];$a=$table[$match->participant_a_id];$b=$table[$match->participant_b_id];$table[$match->participant_a_id]['difference']+=(float)$match->score_a-(float)$match->score_b;$table[$match->participant_b_id]['difference']+=(float)$match->score_b-(float)$match->score_a;if((float)$match->score_a===(float)$match->score_b){$table[$match->participant_a_id]['points']++;$table[$match->participant_b_id]['points']++;}elseif((float)$match->score_a>(float)$match->score_b)$table[$match->participant_a_id]['points']+=3;else $table[$match->participant_b_id]['points']+=3;}
            $ranked=collect($table)->sortByDesc(fn($row)=>sprintf('%05d:%08.2f',$row['points'],$row['difference']))->values();$qualifiers[]=$ranked[0]['id'];$qualifiers[]=$ranked[1]['id'];
        }
        $participants=Registration::whereIn('id',$qualifiers)->get()->keyBy('id');$ordered=collect($qualifiers)->map(fn($id)=>$participants[$id]);$size=2;while($size<$ordered->count())$size*=2;$slots=array_pad($ordered->all(),$size,null);
        $this->createEliminationMatches($draw,$slots,['third_place'=>$draw->settings['third_place']??false],false,'knockout',(int)$draw->matches()->max('match_number')+1);
        return $this->drawPayload($draw->fresh());
    }

    public function publicView(string $slug)
    {
        $competition=Competition::where('event_edition_id', EventEdition::resolveCurrent(true)->id)->where('slug',$slug)->firstOrFail();$draw=$competition->tournamentDraws()->where('status','locked')->latest('version')->first();
        return ['competition'=>$competition->only(['id','title','slug']),'draw'=>$draw?$this->drawPayload($draw):null];
    }
}
