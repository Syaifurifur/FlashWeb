<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\JudgeAssignment;
use App\Models\JudgeScore;
use App\Models\JudgingCriterion;
use App\Models\Registration;
use App\Models\User;
use App\Models\EventEdition;
use App\Models\CompetitionResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JudgingController extends Controller
{
    private function competitions(Request $request)
    {
        $editionId = EventEdition::resolveCurrent()->id;
        return $request->user()->role === 'super_admin' || $request->user()->hasPermission('competitions.manage')
            ? Competition::where('event_edition_id', $editionId)
            : Competition::where('event_edition_id', $editionId)->whereKey($request->user()->competition_id);
    }

    private function authorizeCompetition(Request $request, Competition $competition): void
    {
        abort_unless($this->competitions($request)->whereKey($competition->id)->exists(), 403);
    }

    public function manage(Request $request)
    {
        $options = $this->competitions($request)->where('category','!=','Sport Competition')->orderBy('title')->get(['id','title']);
        $competition = $request->filled('competition_id')
            ? $this->competitions($request)->whereKey($request->integer('competition_id'))->firstOrFail()
            : $this->competitions($request)->where('category','!=','Sport Competition')->orderBy('title')->first();
        if (! $competition) return ['competitions'=>$options,'competition'=>null,'criteria'=>[],'works'=>[],'judges'=>[],'assignments'=>[]];

        $works = Registration::where('competition_id',$competition->id)->whereNotNull('work_submission_url')
            ->with(['judgeAssignments.judge:id,name,email','judgeAssignments.scores'])
            ->latest('work_submitted_at')->get(['id','competition_id','ticket_code','full_name','team_name','school_name','work_submission_url','work_submitted_at','work_verification_status','work_verification_note']);
        $assignments = JudgeAssignment::where('competition_id',$competition->id)
            ->with(['judge:id,name,email','registration:id,full_name,team_name,ticket_code','scores'])->latest()->get();

        return [
            'competitions'=>$options,
            'competition'=>$competition->only(['id','title','judging_guide','judging_locked_at','results_announced_at']),
            'criteria'=>$competition->judgingCriteria()->get(),
            'works'=>$works,
            'judges'=>User::where('role','judge')->where('is_active',true)->orderBy('name')->get(['id','name','email']),
            'assignments'=>$assignments,
            'progress'=>['total'=>$assignments->count(),'final'=>$assignments->where('status','final')->count(),'draft'=>$assignments->where('status','draft')->count()],
        ];
    }

    public function configure(Request $request, Competition $competition)
    {
        $this->authorizeCompetition($request,$competition);
        abort_if($competition->judging_locked_at, 422, 'Penilaian telah dikunci.');
        abort_if($competition->judgeAssignments()->where('status','final')->exists(), 422, 'Kriteria tidak dapat diubah karena sudah ada nilai final.');
        $data=$request->validate([
            'judging_guide'=>'required|string|max:20000','criteria'=>'required|array|min:1|max:20',
            'criteria.*.name'=>'required|string|max:160','criteria.*.description'=>'nullable|string|max:1000',
            'criteria.*.max_score'=>'required|numeric|min:1|max:1000',
        ]);
        DB::transaction(function()use($competition,$data){
            $competition->update(['judging_guide'=>$data['judging_guide']]);
            $competition->judgingCriteria()->delete();
            foreach($data['criteria'] as $index=>$criterion)$competition->judgingCriteria()->create($criterion+['sort_order'=>$index+1]);
        });
        return $competition->fresh()->load('judgingCriteria');
    }

    public function verifyWork(Request $request, Registration $registration)
    {
        $this->authorizeCompetition($request,$registration->competition);
        abort_unless($registration->work_submission_url,422,'Peserta belum mengirim karya.');
        abort_if($registration->competition->judging_locked_at,422,'Penilaian telah dikunci.');
        $data=$request->validate(['status'=>'required|in:verified,rejected','note'=>'nullable|string|max:2000']);
        $registration->update(['work_verification_status'=>$data['status'],'work_verification_note'=>$data['note']??null,'work_verified_by'=>$request->user()->id,'work_verified_at'=>now()]);
        return $registration;
    }

    public function assign(Request $request, Registration $registration)
    {
        $this->authorizeCompetition($request,$registration->competition);
        abort_if($registration->competition->judging_locked_at,422,'Penilaian telah dikunci.');
        abort_unless($registration->work_verification_status==='verified',422,'Karya harus diverifikasi sebelum dibagikan.');
        abort_unless($registration->competition->judgingCriteria()->exists(),422,'Tetapkan kriteria penilaian terlebih dahulu.');
        $data=$request->validate(['judge_id'=>'required|exists:users,id']);
        $judge=User::findOrFail($data['judge_id']);
        abort_unless($judge->role==='judge'&&$judge->is_active,422,'Akun yang dipilih bukan juri aktif.');
        $assignment=JudgeAssignment::firstOrCreate(
            ['registration_id'=>$registration->id,'judge_id'=>$judge->id],
            ['competition_id'=>$registration->competition_id,'assigned_by'=>$request->user()->id]
        );
        return response()->json($assignment->load('judge:id,name,email'),$assignment->wasRecentlyCreated?201:200);
    }

    public function unassign(Request $request, JudgeAssignment $assignment)
    {
        $this->authorizeCompetition($request,$assignment->competition);
        abort_if($assignment->competition->judging_locked_at||$assignment->status==='final',422,'Penugasan final atau terkunci tidak dapat dihapus.');
        $assignment->delete();
        return response()->noContent();
    }

    public function lock(Request $request, Competition $competition)
    {
        $this->authorizeCompetition($request,$competition);
        $assignments=$competition->judgeAssignments();
        abort_unless($assignments->exists(),422,'Belum ada penugasan juri.');
        abort_if((clone $assignments)->where('status','!=','final')->exists(),422,'Semua juri harus mengirim nilai final sebelum dikunci.');
        $competition->update(['judging_locked_at'=>now()]);
        return $competition;
    }

    public function announce(Request $request, Competition $competition)
    {
        $this->authorizeCompetition($request,$competition);
        abort_unless($competition->judging_locked_at,422,'Kunci penilaian sebelum mengumumkan hasil.');
        DB::transaction(function () use ($competition) {
            $competition->update(['results_announced_at'=>now()]);
            $ranked = $competition->judgeAssignments()->where('status', 'final')
                ->with('scores')->get()->groupBy('registration_id')
                ->map(fn ($items, $registrationId) => [
                    'registration_id'=>(int) $registrationId,
                    'score'=>round($items->avg(fn ($assignment) => $assignment->scores->sum('score')), 2),
                ])->sortByDesc('score')->values()->take(3);
            CompetitionResult::where('competition_id', $competition->id)->where('source', 'judging')->delete();
            $ranked->each(fn ($result, $index) => CompetitionResult::create([
                'competition_id'=>$competition->id,
                'registration_id'=>$result['registration_id'],
                'rank'=>$index + 1,
                'title'=>'Juara '.($index + 1),
                'source'=>'judging',
                'score'=>$result['score'],
                'announced_at'=>now(),
            ]));
        });
        return $competition;
    }

    public function judgeAssignments(Request $request)
    {
        return JudgeAssignment::where('judge_id',$request->user()->id)
            ->whereHas('competition', fn ($query) => $query->where('event_edition_id', EventEdition::resolveCurrent()->id))
            ->with(['competition:id,title,judging_guide,judging_locked_at,results_announced_at','competition.judgingCriteria','registration:id,competition_id,ticket_code,full_name,team_name,school_name,work_submission_url,work_submitted_at','scores'])
            ->latest()->get();
    }

    public function score(Request $request, JudgeAssignment $assignment)
    {
        abort_unless($assignment->judge_id===$request->user()->id,403);
        abort_if($assignment->competition->judging_locked_at,422,'Penilaian telah dikunci.');
        $data=$request->validate(['action'=>'required|in:draft,final','notes'=>'nullable|string|max:5000','scores'=>'required|array','scores.*'=>'numeric|min:0']);
        $criteria=$assignment->competition->judgingCriteria()->get();
        $submitted=collect($data['scores'])->mapWithKeys(fn($score,$id)=>[(int)$id=>(float)$score]);
        if($data['action']==='final'&&$criteria->pluck('id')->diff($submitted->keys())->isNotEmpty())return response()->json(['message'=>'Semua kriteria wajib diberi nilai sebelum final.'],422);
        foreach($submitted as $criterionId=>$score){
            $criterion=$criteria->firstWhere('id',$criterionId);
            if(!$criterion||$score>$criterion->max_score)return response()->json(['message'=>'Nilai melebihi batas maksimum kriteria.'],422);
            JudgeScore::updateOrCreate(['judge_assignment_id'=>$assignment->id,'judging_criterion_id'=>$criterionId],['score'=>$score]);
        }
        $assignment->update(['notes'=>$data['notes']??null,'status'=>$data['action'],'submitted_at'=>$data['action']==='final'?now():null]);
        return $assignment->fresh()->load('scores');
    }

    public function participantResults(Request $request)
    {
        $registrationIds=$request->user()->registrations()->pluck('id');
        $assignments=JudgeAssignment::whereIn('registration_id',$registrationIds)->where('status','final')
            ->whereHas('competition',fn($q)=>$q->whereNotNull('results_announced_at'))
            ->with(['competition:id,title,results_announced_at','competition.judgingCriteria','registration:id,competition_id,ticket_code','scores'])->get();
        return $assignments->groupBy('registration_id')->map(function($items){
            $first=$items->first();$criteria=$first->competition->judgingCriteria;
            return ['registration_id'=>$first->registration_id,'competition'=>$first->competition,'ticket_code'=>$first->registration->ticket_code,
                'total_score'=>round($items->avg(fn($a)=>$a->scores->sum('score')),2),'judge_count'=>$items->count(),
                'criteria'=>$criteria->map(fn($c)=>['name'=>$c->name,'max_score'=>$c->max_score,'score'=>round($items->avg(fn($a)=>optional($a->scores->firstWhere('judging_criterion_id',$c->id))->score??0),2)])->values()];
        })->values();
    }
}
