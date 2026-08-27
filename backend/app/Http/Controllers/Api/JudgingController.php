<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\CompetitionSession;
use App\Models\EventEdition;
use App\Models\JudgeAssignment;
use App\Models\JudgeScore;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JudgingController extends Controller
{
    private function competitions(Request $request)
    {
        return $request->user()->manageableCompetitionsQuery(EventEdition::resolveCurrent()->id);
    }

    private function authorizeCompetition(Request $request, Competition $competition): void
    {
        abort_unless($this->competitions($request)->whereKey($competition->id)->exists(), 403);
    }

    private function accessibleSessions(Request $request, Competition $competition)
    {
        $query = $competition->sessions()->where('is_active', true);
        if ($request->user()->managesAllLocations()) return $query;

        return $query->whereIn(
            'competition_sessions.id',
            $request->user()->manageableCompetitionSessionsQuery(EventEdition::resolveCurrent()->id)
                ->where('competition_sessions.competition_id', $competition->id)
                ->select('competition_sessions.id')
        );
    }

    private function scopeOptions(Request $request): Collection
    {
        return $this->competitions($request)
            ->where('category', '!=', 'Sport Competition')
            ->orderBy('title')->get()
            ->flatMap(function (Competition $competition) use ($request) {
                $hasSessions = $competition->sessions()->exists();
                $sessions = $this->accessibleSessions($request, $competition)->get();
                if (! $hasSessions) return [[
                    'competition_id'=>$competition->id, 'session_id'=>null, 'label'=>$competition->title,
                    'competition_title'=>$competition->title, 'city'=>null, 'venue'=>null,
                ]];

                return $sessions->map(fn (CompetitionSession $session) => [
                    'competition_id'=>$competition->id, 'session_id'=>$session->id,
                    'label'=>$competition->title.' · '.$session->city,
                    'competition_title'=>$competition->title, 'city'=>$session->city, 'venue'=>$session->venue,
                ]);
            })->values();
    }

    private function selectedScope(Request $request): ?array
    {
        $scopes = $this->scopeOptions($request);
        if ($request->filled('session_id')) return $scopes->first(fn ($scope) => (int) $scope['session_id'] === $request->integer('session_id'));
        if ($request->filled('competition_id')) return $scopes->first(fn ($scope) => (int) $scope['competition_id'] === $request->integer('competition_id'));
        return $scopes->first();
    }

    private function requestedSession(Request $request, Competition $competition): ?CompetitionSession
    {
        if (! $competition->sessions()->exists()) return null;
        abort_unless($request->filled('competition_session_id'), 422, 'Pilih tempat pelaksanaan terlebih dahulu.');
        return $this->accessibleSessions($request, $competition)
            ->whereKey($request->integer('competition_session_id'))->firstOrFail();
    }

    private function authorizeRegistration(Request $request, Registration $registration): ?CompetitionSession
    {
        $this->authorizeCompetition($request, $registration->competition);
        $session = $registration->competitionSession;
        if ($session) {
            abort_unless($this->accessibleSessions($request, $registration->competition)->whereKey($session->id)->exists(), 403);
        } elseif ($registration->competition->sessions()->exists()) {
            abort(403);
        }
        return $session;
    }

    private function lockedAt(Competition $competition, ?CompetitionSession $session)
    {
        return $session?->judging_locked_at ?? $competition->judging_locked_at;
    }

    private function announcedAt(Competition $competition, ?CompetitionSession $session)
    {
        return $session?->results_announced_at ?? $competition->results_announced_at;
    }

    private function assignmentsQuery(Competition $competition, ?CompetitionSession $session)
    {
        return JudgeAssignment::where('competition_id', $competition->id)
            ->whereHas('registration', fn ($query) => $query->where('competition_session_id', $session?->id));
    }

    public function manage(Request $request)
    {
        $scopes = $this->scopeOptions($request);
        $options = $this->competitions($request)->where('category','!=','Sport Competition')->orderBy('title')->get(['id','title']);
        $scope = $this->selectedScope($request);
        if (! $scope) return ['scopes'=>$scopes,'competitions'=>$options,'competition'=>null,'session'=>null,'criteria'=>[],'works'=>[],'judges'=>[],'assignments'=>[]];

        $competition = $this->competitions($request)->whereKey($scope['competition_id'])->firstOrFail();
        $session = $scope['session_id'] ? $this->accessibleSessions($request, $competition)->whereKey($scope['session_id'])->firstOrFail() : null;
        $lockedAt = $this->lockedAt($competition, $session);
        $announcedAt = $this->announcedAt($competition, $session);

        $works = Registration::where('competition_id', $competition->id)
            ->where('competition_session_id', $session?->id)->whereNotNull('work_submission_url')
            ->with(['judgeAssignments.judge:id,name,email','judgeAssignments.scores'])
            ->latest('work_submitted_at')->get([
                'id','competition_id','competition_session_id','ticket_code','full_name','team_name','school_name',
                'work_submission_url','work_submitted_at','work_verification_status','work_verification_note',
            ]);
        $assignments = $this->assignmentsQuery($competition, $session)
            ->with(['judge:id,name,email','registration:id,full_name,team_name,ticket_code','scores'])->latest()->get();

        return [
            'scopes'=>$scopes,
            'competitions'=>$options,
            'competition'=>[...$competition->only(['id','title','judging_guide']), 'judging_locked_at'=>$lockedAt, 'results_announced_at'=>$announcedAt],
            'session'=>$session?->only(['id','competition_id','city','venue','judging_locked_at','results_announced_at']),
            'criteria'=>$competition->judgingCriteria()->get(), 'works'=>$works,
            'judges'=>User::where('role','judge')->where('is_active',true)->orderBy('name')->get(['id','name','email']),
            'assignments'=>$assignments,
            'progress'=>['total'=>$assignments->count(),'final'=>$assignments->where('status','final')->count(),'draft'=>$assignments->where('status','draft')->count()],
        ];
    }

    public function configure(Request $request, Competition $competition)
    {
        $this->authorizeCompetition($request, $competition);
        $session = $this->requestedSession($request, $competition);
        abort_if($this->lockedAt($competition, $session), 422, 'Penilaian tempat ini telah dikunci.');
        abort_if($competition->judgeAssignments()->where('status','final')->exists(), 422, 'Kriteria tidak dapat diubah karena sudah ada nilai final.');
        $data=$request->validate([
            'competition_session_id'=>'nullable|integer|exists:competition_sessions,id',
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
        $session = $this->authorizeRegistration($request, $registration);
        abort_unless($registration->work_submission_url,422,'Peserta belum mengirim karya.');
        abort_if($this->lockedAt($registration->competition, $session),422,'Penilaian tempat ini telah dikunci.');
        $data=$request->validate(['status'=>'required|in:verified,rejected','note'=>'nullable|string|max:2000']);
        $registration->update(['work_verification_status'=>$data['status'],'work_verification_note'=>$data['note']??null,'work_verified_by'=>$request->user()->id,'work_verified_at'=>now()]);
        return $registration;
    }

    public function assign(Request $request, Registration $registration)
    {
        $session = $this->authorizeRegistration($request, $registration);
        abort_if($this->lockedAt($registration->competition, $session),422,'Penilaian tempat ini telah dikunci.');
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
        $session = $this->authorizeRegistration($request, $assignment->registration);
        abort_if($this->lockedAt($assignment->competition, $session)||$assignment->status==='final',422,'Penugasan final atau terkunci tidak dapat dihapus.');
        $assignment->delete();
        return response()->noContent();
    }

    public function lock(Request $request, Competition $competition)
    {
        $this->authorizeCompetition($request,$competition);
        $request->validate(['competition_session_id'=>'nullable|integer|exists:competition_sessions,id']);
        $session = $this->requestedSession($request, $competition);
        $assignments=$this->assignmentsQuery($competition, $session);
        abort_unless($assignments->exists(),422,'Belum ada penugasan juri pada tempat ini.');
        abort_if((clone $assignments)->where('status','!=','final')->exists(),422,'Semua juri pada tempat ini harus mengirim nilai final sebelum dikunci.');
        ($session ?: $competition)->update(['judging_locked_at'=>now()]);
        return $session?->fresh() ?? $competition->fresh();
    }

    public function announce(Request $request, Competition $competition)
    {
        $this->authorizeCompetition($request,$competition);
        $request->validate(['competition_session_id'=>'nullable|integer|exists:competition_sessions,id']);
        $session = $this->requestedSession($request, $competition);
        abort_unless($this->lockedAt($competition, $session),422,'Kunci penilaian tempat ini sebelum mengumumkan hasil.');
        DB::transaction(function () use ($competition, $session) {
            ($session ?: $competition)->update(['results_announced_at'=>now()]);
            $ranked = $this->assignmentsQuery($competition, $session)->where('status', 'final')
                ->with('scores')->get()->groupBy('registration_id')
                ->map(fn ($items, $registrationId) => ['registration_id'=>(int) $registrationId,'score'=>round($items->avg(fn ($assignment) => $assignment->scores->sum('score')), 2)])
                ->sortByDesc('score')->values()->take(3);
            CompetitionResult::where('competition_id', $competition->id)->where('competition_session_id', $session?->id)->where('source', 'judging')->delete();
            $ranked->each(fn ($result, $index) => CompetitionResult::create([
                'competition_id'=>$competition->id, 'competition_session_id'=>$session?->id,
                'registration_id'=>$result['registration_id'], 'rank'=>$index + 1,
                'title'=>'Juara '.($index + 1), 'source'=>'judging', 'score'=>$result['score'], 'announced_at'=>now(),
            ]));
        });
        return $session?->fresh() ?? $competition->fresh();
    }

    public function judgeAssignments(Request $request)
    {
        return JudgeAssignment::where('judge_id',$request->user()->id)
            ->whereHas('competition', fn ($query) => $query->where('event_edition_id', EventEdition::resolveCurrent()->id))
            ->with([
                'competition:id,title,judging_guide,judging_locked_at,results_announced_at','competition.judgingCriteria',
                'registration:id,competition_id,competition_session_id,ticket_code,full_name,team_name,school_name,work_submission_url,work_submitted_at',
                'registration.competitionSession:id,competition_id,city,venue,judging_locked_at,results_announced_at','scores',
            ])->latest()->get()->each(function (JudgeAssignment $assignment) {
                $session = $assignment->registration->competitionSession;
                $assignment->competition->setAttribute('judging_locked_at', $this->lockedAt($assignment->competition, $session));
                $assignment->competition->setAttribute('results_announced_at', $this->announcedAt($assignment->competition, $session));
                $assignment->setAttribute('competition_session', $session);
            });
    }

    public function score(Request $request, JudgeAssignment $assignment)
    {
        abort_unless($assignment->judge_id===$request->user()->id,403);
        $session = $assignment->registration->competitionSession;
        abort_if($this->lockedAt($assignment->competition, $session),422,'Penilaian tempat ini telah dikunci.');
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
            ->with([
                'competition:id,title,results_announced_at','competition.judgingCriteria',
                'registration:id,competition_id,competition_session_id,ticket_code',
                'registration.competitionSession:id,competition_id,city,venue,results_announced_at','scores',
            ])->get()->filter(fn (JudgeAssignment $assignment) => (bool) $this->announcedAt($assignment->competition, $assignment->registration->competitionSession));
        return $assignments->groupBy('registration_id')->map(function($items){
            $first=$items->first();$criteria=$first->competition->judgingCriteria;
            return ['registration_id'=>$first->registration_id,'competition'=>$first->competition,
                'competition_session'=>$first->registration->competitionSession,'ticket_code'=>$first->registration->ticket_code,
                'total_score'=>round($items->avg(fn($a)=>$a->scores->sum('score')),2),'judge_count'=>$items->count(),
                'criteria'=>$criteria->map(fn($c)=>['name'=>$c->name,'max_score'=>$c->max_score,'score'=>round($items->avg(fn($a)=>optional($a->scores->firstWhere('judging_criterion_id',$c->id))->score??0),2)])->values()];
        })->values();
    }
}
