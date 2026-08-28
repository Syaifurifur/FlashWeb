<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionSession;
use App\Models\CompetitionVenue;
use App\Models\CompetitionType;
use App\Models\EventEdition;
use App\Models\AccessRole;
use App\Models\Registration;
use App\Models\RegistrationMember;
use App\Models\User;
use App\Services\RegistrationExcelExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ManagementController extends Controller
{
    private function scopeCompetitions(Request $request)
    {
        return $request->user()->manageableCompetitionsQuery(EventEdition::resolveCurrent()->id);
    }

    private function scopeSessions(Request $request)
    {
        return $request->user()->manageableCompetitionSessionsQuery(EventEdition::resolveCurrent()->id);
    }

    private function scopeRegistrations(Request $request)
    {
        return $request->user()->manageableRegistrationsQuery(EventEdition::resolveCurrent()->id);
    }

    private function authorizeRegistration(Request $request, Registration $registration): void
    {
        abort_unless($this->scopeRegistrations($request)->whereKey($registration->id)->exists(), 403);
    }

    public function dashboard(Request $request)
    {
        $competitionIds = $this->scopeCompetitions($request)->pluck('id');
        $regs = $this->scopeRegistrations($request);
        $sessionIds = $request->user()->managesAllLocations() ? null : $this->scopeSessions($request)->pluck('competition_sessions.id');
        $venueQuery = CompetitionVenue::query()->where('event_edition_id', EventEdition::resolveCurrent()->id)->where('is_active', true);
        if ($sessionIds !== null) {
            $venueQuery->whereHas('sessions', fn ($session) => $session->whereIn('competition_sessions.id', $sessionIds));
        }
        $cities = $venueQuery->with(['sessions'=>function ($session) use ($competitionIds, $sessionIds) {
            $session->whereIn('competition_id', $competitionIds);
            if ($sessionIds !== null) $session->whereIn('competition_sessions.id', $sessionIds);
            $session->with('competition:id,title')->withCount([
                'registrations',
                'registrations as approved_registrations_count'=>fn ($registration) => $registration->where('status', 'approved'),
                'registrations as pending_registrations_count'=>fn ($registration) => $registration->where('status', 'pending'),
            ]);
        }])->orderBy('activity_start_date')->orderBy('city')->get()->map(fn (CompetitionVenue $venue) => [
            'id'=>$venue->id,
            'slug'=>$venue->slug,
            'city'=>$venue->city,
            'name'=>$venue->name,
            'field_photo_url'=>$venue->field_photo_url,
            'activity_start_date'=>$venue->activity_start_date?->toDateString(),
            'activity_end_date'=>$venue->activity_end_date?->toDateString(),
            'competitions_count'=>$venue->sessions->pluck('competition_id')->unique()->count(),
            'registrations_count'=>$venue->sessions->sum('registrations_count'),
            'approved_count'=>$venue->sessions->sum('approved_registrations_count'),
            'pending_count'=>$venue->sessions->sum('pending_registrations_count'),
            'quota'=>$venue->sessions->sum('quota'),
            'competition_quotas'=>$venue->sessions
                ->sortBy(fn (CompetitionSession $session) => $session->competition->title)
                ->map(fn (CompetitionSession $session) => [
                    'id'=>$session->id,
                    'title'=>$session->competition->title,
                    'filled'=>(int) $session->registrations_count,
                    'quota'=>(int) $session->quota,
                ])->values(),
        ])->values();
        return [
            'competitions' => $competitionIds->count(), 'registrations' => (clone $regs)->count(),
            'pending' => (clone $regs)->where('status','pending')->count(),
            'approved' => (clone $regs)->where('status','approved')->count(),
            'revenue' => (clone $regs)->where('registrations.status','approved')
                ->leftJoin('competition_sessions','competition_sessions.id','=','registrations.competition_session_id')
                ->join('competitions','competitions.id','=','registrations.competition_id')
                ->sum(DB::raw('COALESCE(competition_sessions.fee, competitions.fee)')),
            'recent' => (clone $regs)->with(['competition:id,title','competitionSession:id,city,venue'])->latest('registrations.created_at')->limit(6)->get(),
            'cities'=>$cities,
        ];
    }

    public function competitions(Request $request)
    {
        $sessionIds = $request->user()->managesAllLocations() ? null : $this->scopeSessions($request)->pluck('competition_sessions.id');
        return $this->scopeCompetitions($request)
            ->with([
                'competitionType:id,name,slug,category_group',
                'sessions'=>function ($query) use ($sessionIds) {
                    if ($sessionIds !== null) $query->whereIn('competition_sessions.id', $sessionIds);
                    $query->with(['pic:id,name,whatsapp','supervisor:id,name,whatsapp','pics:id,name,whatsapp','supervisors:id,name,whatsapp'])
                        ->withCount('registrations');
                },
            ])
            ->withCount([
                'registrations'=>fn ($query) => $sessionIds === null ? $query : $query->whereIn('competition_session_id', $sessionIds),
                'pics',
            ])->latest()->get();
    }

    public function storeCompetition(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $this->competitionData($request);
            $sessions = $data['sessions'] ?? [];
            unset($data['sessions']);
            $competition = Competition::create($data);
            $this->syncSessions($competition, $sessions);

            return response()->json($competition->fresh()->load(['competitionType:id,name,slug,category_group','sessions'=>fn ($query) => $query->with(['pics:id,name,whatsapp','supervisors:id,name,whatsapp'])->withCount('registrations')]), 201);
        });
    }

    public function updateCompetition(Request $request, Competition $competition)
    {
        abort_unless($this->scopeCompetitions($request)->whereKey($competition->id)->exists(), 403);
        return DB::transaction(function () use ($request, $competition) {
            $data = $this->competitionData($request, $competition);
            $hasSessions = array_key_exists('sessions', $data);
            $sessions = $data['sessions'] ?? [];
            unset($data['sessions']);
            $competition->update($data);
            if ($hasSessions) $this->syncSessions($competition, $sessions);

            return $competition->fresh()->load(['competitionType:id,name,slug,category_group','sessions'=>fn ($query) => $query->with(['pics:id,name,whatsapp','supervisors:id,name,whatsapp'])->withCount('registrations')]);
        });
    }

    public function destroyCompetition(Request $request, Competition $competition) { abort_unless($this->scopeCompetitions($request)->whereKey($competition->id)->exists(), 404); $competition->delete(); return response()->noContent(); }

    public function updateGuides(Request $request, Competition $competition)
    {
        abort_unless($this->scopeCompetitions($request)->whereKey($competition->id)->exists(), 403);
        $data = $request->validate([
            'guides'=>'required|array|min:1|max:30',
            'guides.*.title'=>'required|string|max:150',
            'guides.*.content'=>'required|string|max:10000',
        ]);
        $competition->update(['guides'=>$data['guides'], 'requirements'=>[]]);

        return $competition->fresh();
    }

    public function updateDownloadableDocuments(Request $request, Competition $competition)
    {
        abort_unless($this->scopeCompetitions($request)->whereKey($competition->id)->exists(), 403);
        $data = $request->validate([
            'documents'=>'nullable|array|max:20',
            'documents.*.title'=>'required|string|max:150',
            'documents.*.description'=>'nullable|string|max:500',
            'documents.*.file_path'=>'nullable|string|max:1000',
            'documents.*.original_name'=>'nullable|string|max:255',
            'document_files'=>'nullable|array|max:20',
            'document_files.*'=>'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip|max:10240',
        ]);

        $currentPaths = collect($competition->downloadable_documents ?? [])->pluck('file_path')->filter();
        $documents = [];
        foreach ($data['documents'] ?? [] as $index => $document) {
            $path = $document['file_path'] ?? null;
            $name = $document['original_name'] ?? null;
            if ($request->hasFile("document_files.$index")) {
                $file = $request->file("document_files.$index");
                $path = $file->store('competitions/'.$competition->id.'/downloads', 'public');
                $name = $file->getClientOriginalName();
            } elseif (! $path || ! $currentPaths->contains($path)) {
                return response()->json(['message'=>"Pilih file untuk dokumen ke-".($index + 1).'.'], 422);
            }
            $documents[] = [
                'title'=>$document['title'],
                'description'=>$document['description'] ?? '',
                'file_path'=>$path,
                'original_name'=>$name ?: basename($path),
            ];
        }

        $retainedPaths = collect($documents)->pluck('file_path');
        $currentPaths->diff($retainedPaths)->each(fn ($path) => Storage::disk('public')->delete($path));
        $competition->update(['downloadable_documents'=>$documents]);

        return $competition->fresh();
    }

    private function competitionData(Request $request, ?Competition $competition = null): array
    {
        $data = $request->validate([
            'title'=>'required|string|max:180',
            'competition_type_id'=>'nullable|integer|exists:competition_types,id',
            'category'=>'nullable|required_without:competition_type_id|in:Sport Competition,Talent Competition,Science Competition',
            'short_description'=>'required|string|max:300',
            'description'=>'required|string',
            'poster_url'=>'nullable|url|max:500',
            'guides'=>'required|array|min:1|max:30',
            'guides.*.title'=>'required|string|max:150',
            'guides.*.content'=>'required|string|max:10000',
            'sessions'=>'sometimes|array|max:30',
            'sessions.*.id'=>'nullable|integer',
            'sessions.*.venue_id'=>'nullable|integer|exists:competition_venues,id',
            'sessions.*.pic_user_id'=>'nullable|integer|exists:users,id',
            'sessions.*.supervisor_user_id'=>'nullable|integer|exists:users,id',
            'sessions.*.pic_slots'=>'nullable|integer|min:1|max:10',
            'sessions.*.supervisor_slots'=>'nullable|integer|min:1|max:10',
            'sessions.*.pic_ids'=>'nullable|array|min:1|max:10',
            'sessions.*.pic_ids.*'=>'integer|exists:users,id',
            'sessions.*.supervisor_ids'=>'nullable|array|min:1|max:10',
            'sessions.*.supervisor_ids.*'=>'integer|exists:users,id',
            'sessions.*.city'=>'nullable|string|max:120|required_without:sessions.*.venue_id',
            'sessions.*.venue'=>'nullable|string|max:180|required_without:sessions.*.venue_id',
            'sessions.*.quota'=>'nullable|integer|min:1',
            'sessions.*.fee'=>'nullable|numeric|min:0',
            'sessions.*.team_update_deadline_at'=>'nullable|date',
            'sessions.*.submission_start_at'=>'nullable|date|required_with:sessions.*.submission_end_at',
            'sessions.*.submission_end_at'=>'nullable|date|required_with:sessions.*.submission_start_at|after:sessions.*.submission_start_at',
            'sessions.*.timeline'=>'nullable|array|max:30',
            'sessions.*.timeline.*.label'=>'required_with:sessions.*.timeline|string|max:120',
            'sessions.*.timeline.*.type'=>'nullable|in:single,range',
            'sessions.*.timeline.*.date'=>'nullable|date',
            'sessions.*.timeline.*.start_date'=>'nullable|date',
            'sessions.*.timeline.*.end_date'=>'nullable|date',
            'sessions.*.whatsapp_number'=>['nullable','string','max:30','regex:/^[0-9+()\-\s]+$/'],
            'sessions.*.whatsapp_group_url'=>['nullable','url','max:500','regex:/^https:\/\/(chat\.)?whatsapp\.com\//i'],
            'sessions.*.notes'=>'nullable|string|max:2000',
            'sessions.*.sort_order'=>'nullable|integer|min:0|max:1000',
            'sessions.*.is_active'=>'nullable|boolean',
            'sessions.*.activity_start_date'=>'nullable|date',
            'sessions.*.activity_end_date'=>'nullable|date',
            'sessions.*.competition_start_date'=>'nullable|date',
            'sessions.*.competition_end_date'=>'nullable|date',
            'quota'=>'nullable|integer|min:1',
            'fee'=>'nullable|numeric|min:0',
            'timeline'=>'nullable|array|max:30',
            'team_update_deadline_at'=>'nullable|date',
            'submission_start_at'=>'nullable|date',
            'submission_end_at'=>'nullable|date',
            'document_upload_deadline_at'=>'nullable|date',
            'location'=>'nullable|string|max:180',
            'whatsapp_group_url'=>['nullable','url','max:500','regex:/^https:\/\/(chat\.)?whatsapp\.com\//i'],
            'bank_name'=>'nullable|string|max:120',
            'bank_account_number'=>'nullable|string|max:80',
            'bank_account_holder'=>'nullable|string|max:180',
            'payment_note'=>'nullable|string|max:500',
            'participation_type'=>'nullable|in:individual,team',
            'team_size'=>'nullable|integer|min:1|max:20',
            'official_count'=>'nullable|integer|min:0|max:20',
            'pic_slots'=>'nullable|integer|min:1|max:10',
            'is_featured'=>'nullable|boolean',
        ]);

        if (! empty($data['competition_type_id'])) {
            $type = CompetitionType::findOrFail($data['competition_type_id']);
            if (! $type->is_active && $competition?->competition_type_id !== $type->id) {
                abort(422, 'Jenis lomba yang dipilih sedang nonaktif.');
            }
            $data['category'] = $type->category_group;
        }

        $data['participation_type'] = $data['participation_type'] ?? $competition?->participation_type ?? 'individual';
        $data['team_size'] = $data['participation_type'] === 'individual' ? 1 : ($data['team_size'] ?? $competition?->team_size ?? 1);
        $data['official_count'] = $data['participation_type'] === 'individual' ? 0 : ($data['official_count'] ?? $competition?->official_count ?? 0);
        $data['pic_slots'] = $data['pic_slots'] ?? $competition?->pic_slots ?? 1;
        $data['is_featured'] = (bool) ($data['is_featured'] ?? $competition?->is_featured ?? false);
        $data['requirements'] = [];

        $legacyTimeline = $this->normalizeTimeline($data['timeline'] ?? $competition?->timeline ?? []);
        $legacyDeadline = $data['team_update_deadline_at'] ?? $competition?->team_update_deadline_at?->toISOString();
        $legacyFee = $data['fee'] ?? $competition?->fee ?? 0;
        $legacyQuota = $data['quota'] ?? $competition?->quota ?? 1;
        $legacyLocation = $data['location'] ?? $competition?->location ?? 'Belum ditentukan';
        $legacyWhatsappGroup = $data['whatsapp_group_url'] ?? $competition?->whatsapp_group_url;
        $sessions = collect($data['sessions'] ?? [])->map(function (array $session) use ($data, $legacyTimeline, $legacyDeadline, $legacyFee) {
            $venue = ! empty($session['venue_id']) ? CompetitionVenue::find($session['venue_id']) : null;
            if ($venue && $venue->event_edition_id !== EventEdition::resolveCurrent()->id) abort(422, 'Kota pelaksanaan harus berasal dari tahun yang sedang dipilih.');
            // Kampus hanya menentukan kota dan venue. PIC/SPV wajib berasal dari
            // pilihan pengguna pada sesi lomba, bukan petugas bawaan master kampus.
            $picIds = collect($session['pic_ids'] ?? [($session['pic_user_id'] ?? null)])->filter()->map(fn ($id) => (int) $id)->unique()->values();
            $supervisorIds = collect($session['supervisor_ids'] ?? [($session['supervisor_user_id'] ?? null)])->filter()->map(fn ($id) => (int) $id)->unique()->values();
            $picSlots = (int) ($session['pic_slots'] ?? max(1, $picIds->count()));
            $supervisorSlots = (int) ($session['supervisor_slots'] ?? max(1, $supervisorIds->count()));
            $cityName = $venue?->city ?? ($session['city'] ?? 'kota ini');
            if (array_key_exists('pic_ids', $session) && $picIds->count() !== $picSlots) abort(422, "Jumlah PIC untuk {$cityName} harus sama dengan jumlah slot PIC.");
            if (array_key_exists('supervisor_ids', $session) && $supervisorIds->count() !== $supervisorSlots) abort(422, "Jumlah SPV untuk {$cityName} harus sama dengan jumlah slot SPV.");
            $pics = User::whereIn('id', $picIds)->get();
            $supervisors = User::whereIn('id', $supervisorIds)->get();
            if ($pics->count() !== $picIds->count() || $pics->contains(fn ($pic) => $pic->role !== 'pic' || ! $pic->is_active || empty($pic->whatsapp))) abort(422, 'Seluruh petugas PIC kota harus menggunakan akun PIC aktif dengan nomor WhatsApp.');
            if ($supervisors->count() !== $supervisorIds->count() || $supervisors->contains(fn ($supervisor) => $supervisor->role !== 'spv' || ! $supervisor->is_active || empty($supervisor->whatsapp))) abort(422, 'Seluruh petugas SPV kota harus menggunakan akun SPV aktif dengan nomor WhatsApp.');
            $pic = $pics->firstWhere('id', $picIds->first());
            $supervisor = $supervisors->firstWhere('id', $supervisorIds->first());
            $timeline = $this->normalizeTimeline($session['timeline'] ?? $legacyTimeline);
            $firstTimelineDate = collect($timeline)->map(fn ($entry) => $entry['type'] === 'range' ? $entry['start_date'] : $entry['date'])->filter()->min();
            $lastTimelineDate = collect($timeline)->map(fn ($entry) => $entry['type'] === 'range' ? $entry['end_date'] : $entry['date'])->filter()->max();
            $fallbackDate = $firstTimelineDate ?: now('Asia/Jakarta')->toDateString();

            return array_merge($session, [
                'city'=>$venue?->city ?? $session['city'] ?? '',
                'venue'=>$venue?->name ?? $session['venue'] ?? '',
                'pic_user_id'=>$pic?->id,
                'supervisor_user_id'=>$supervisor?->id,
                'pic_slots'=>$picSlots,
                'supervisor_slots'=>$supervisorSlots,
                'pic_ids'=>$picIds->all(),
                'supervisor_ids'=>$supervisorIds->all(),
                'quota'=>(int) ($session['quota'] ?? $data['quota'] ?? 1),
                'fee'=>(float) ($session['fee'] ?? $legacyFee),
                'team_update_deadline_at'=>$session['team_update_deadline_at'] ?? $legacyDeadline ?? \Carbon\Carbon::parse($lastTimelineDate ?: $fallbackDate, 'Asia/Jakarta')->endOfDay()->utc(),
                'submission_start_at'=>$data['category'] === 'Sport Competition' ? null : ($session['submission_start_at'] ?? $data['submission_start_at'] ?? null),
                'submission_end_at'=>$data['category'] === 'Sport Competition' ? null : ($session['submission_end_at'] ?? $data['submission_end_at'] ?? null),
                'timeline'=>$timeline,
                'whatsapp_number'=>$session['whatsapp_number'] ?? $pic?->whatsapp,
                'activity_start_date'=>$venue?->activity_start_date?->toDateString() ?? $session['activity_start_date'] ?? $fallbackDate,
                'activity_end_date'=>$venue?->activity_end_date?->toDateString() ?? $session['activity_end_date'] ?? $lastTimelineDate ?? $fallbackDate,
                'competition_start_date'=>$session['competition_start_date'] ?? $firstTimelineDate ?? $fallbackDate,
                'competition_end_date'=>$session['competition_end_date'] ?? $lastTimelineDate ?? $fallbackDate,
            ]);
        })->values();
        $data['sessions'] = $sessions->all();

        $activeSessions = $sessions->filter(fn ($session) => ($session['is_active'] ?? true) !== false);
        $fallbackDate = now('Asia/Jakarta')->toDateString();
        $data['quota'] = $activeSessions->isNotEmpty() ? max(1, (int) $activeSessions->sum('quota')) : (int) $legacyQuota;
        $data['fee'] = (float) ($activeSessions->isNotEmpty() ? $activeSessions->min('fee') : $legacyFee);
        $data['location'] = $activeSessions->count() > 1 ? $activeSessions->count().' lokasi' : ($activeSessions->first()['city'] ?? $legacyLocation);
        $data['timeline'] = $activeSessions->first()['timeline'] ?? $legacyTimeline;
        $timelineDates = $activeSessions->isNotEmpty()
            ? $activeSessions->flatMap(fn ($session) => collect($session['timeline'])->map(fn ($entry) => $entry['type'] === 'range' ? $entry['start_date'] : $entry['date']))
            : collect($legacyTimeline)->map(fn ($entry) => $entry['type'] === 'range' ? $entry['start_date'] : $entry['date']);
        $timelineEndDates = $activeSessions->isNotEmpty()
            ? $activeSessions->flatMap(fn ($session) => collect($session['timeline'])->map(fn ($entry) => $entry['type'] === 'range' ? $entry['end_date'] : $entry['date']))
            : collect($legacyTimeline)->map(fn ($entry) => $entry['type'] === 'range' ? $entry['end_date'] : $entry['date']);
        $data['registration_start'] = $timelineDates->filter()->min() ?? $competition?->registration_start?->toDateString() ?? $fallbackDate;
        $latestDeadline = collect($activeSessions)->pluck('team_update_deadline_at')->filter()->max();
        $data['registration_end'] = $latestDeadline ? \Carbon\Carbon::parse($latestDeadline)->toDateString() : ($timelineEndDates->filter()->max() ?? $competition?->registration_end?->toDateString() ?? $fallbackDate);
        $data['event_date'] = collect($activeSessions)->pluck('competition_end_date')->filter()->max() ?? ($timelineEndDates->filter()->max() ?? $competition?->event_date?->toDateString() ?? $fallbackDate);
        $data['team_update_deadline_at'] = collect($activeSessions)->pluck('team_update_deadline_at')->filter()->max() ?? $legacyDeadline;
        $data['document_upload_deadline_at'] = $activeSessions->isNotEmpty() ? $data['team_update_deadline_at'] : ($data['document_upload_deadline_at'] ?? $competition?->document_upload_deadline_at ?? $legacyDeadline);
        $data['submission_start_at'] = collect($activeSessions)->pluck('submission_start_at')->filter()->min();
        $data['submission_end_at'] = collect($activeSessions)->pluck('submission_end_at')->filter()->max();
        $data['whatsapp_group_url'] = $activeSessions->pluck('whatsapp_group_url')->filter()->first() ?? $legacyWhatsappGroup;
        $data['slug'] = Str::slug($data['title']).($competition ? '' : '-'.strtolower(Str::random(4)));

        return $data;
    }

    private function normalizeTimeline(array $timeline): array
    {
        return collect($timeline)->map(function ($entry) {
            $type = $entry['type'] ?? 'single';
            if ($type === 'range') {
                return [
                    'label'=>$entry['label'], 'type'=>'range',
                    'start_date'=>$entry['start_date'], 'end_date'=>$entry['end_date'],
                    'date'=>$entry['start_date'].'|'.$entry['end_date'],
                ];
            }
            return ['label'=>$entry['label'], 'type'=>'single', 'date'=>$entry['date']];
        })->sortBy(fn ($entry) => $entry['type'] === 'range' ? $entry['start_date'] : $entry['date'])->values()->all();
    }

    private function syncSessions(Competition $competition, array $sessions): void
    {
        $submittedIds = collect($sessions)->pluck('id')->filter()->map(fn ($id) => (int) $id);
        $competition->sessions()->whereNotIn('id', $submittedIds)->get()->each(function (CompetitionSession $session) {
            $session->registrations()->exists() ? $session->update(['is_active'=>false]) : $session->delete();
        });

        foreach ($sessions as $index => $sessionData) {
            $id = $sessionData['id'] ?? null;
            $picIds = collect($sessionData['pic_ids'] ?? [($sessionData['pic_user_id'] ?? null)])->filter()->map(fn ($staffId) => (int) $staffId)->unique()->values();
            $supervisorIds = collect($sessionData['supervisor_ids'] ?? [($sessionData['supervisor_user_id'] ?? null)])->filter()->map(fn ($staffId) => (int) $staffId)->unique()->values();
            unset($sessionData['id'], $sessionData['registrations_count'], $sessionData['venue_record'], $sessionData['pic_ids'], $sessionData['supervisor_ids'], $sessionData['pic'], $sessionData['supervisor'], $sessionData['pics'], $sessionData['supervisors']);
            $sessionData['sort_order'] = $sessionData['sort_order'] ?? $index;
            $sessionData['is_active'] = $sessionData['is_active'] ?? true;
            if ($id) {
                $session = $competition->sessions()->whereKey($id)->firstOrFail();
                $session->update($sessionData);
            } else {
                $session = $competition->sessions()->create($sessionData);
            }
            $staff = [];
            foreach ($picIds as $staffIndex => $userId) $staff[$userId] = ['role'=>'pic','sort_order'=>$staffIndex];
            foreach ($supervisorIds as $staffIndex => $userId) $staff[$userId] = ['role'=>'spv','sort_order'=>$staffIndex];
            $session->staff()->sync($staff);
        }
    }

    public function pics() { return User::where('role','pic')->with('competition:id,title')->latest()->get(); }
    public function cityStaff()
    {
        return [
            'pics'=>User::where('role','pic')->where('is_active',true)->orderBy('name')->get(['id','name','email','whatsapp','role']),
            'supervisors'=>User::where('role','spv')->where('is_active',true)->orderBy('name')->get(['id','name','email','whatsapp','role']),
        ];
    }
    public function storePic(Request $request)
    {
        $data=$request->validate(['name'=>'required|string|max:120','email'=>'required|email|unique:users,email','whatsapp'=>['required','regex:/^[0-9+]{10,15}$/'],'password'=>'required|string|min:8']);
        $data['role']='pic';
        $data['competition_id']=null;
        return response()->json(User::create($data),201);
    }
    public function updatePic(Request $request, User $user)
    {
        $data=$request->validate(['name'=>'required|string|max:120','email'=>'required|email|unique:users,email,'.$user->id,'whatsapp'=>['required','regex:/^[0-9+]{10,15}$/'],'password'=>'nullable|string|min:8']);
        $data['competition_id']=null;
        if(empty($data['password'])) unset($data['password']);
        $user->update($data);
        return $user;
    }
    public function destroyPic(User $user) { abort_if($user->role !== 'pic',422); $user->delete(); return response()->noContent(); }

    public function accounts(Request $request)
    {
        $query=User::with('competition:id,title')->withCount('registrations');
        if($request->filled('role')&&$request->role!=='all')$query->where('role',$request->role);
        if($request->filled('search'))$query->where(fn($q)=>$q->where('name','like','%'.$request->search.'%')->orWhere('email','like','%'.$request->search.'%'));
        return $query->latest()->paginate(20);
    }

    public function storeAccount(Request $request)
    {
        $data=$request->validate([
            'name'=>'required|string|max:120','email'=>'required|email|unique:users,email','whatsapp'=>['nullable','regex:/^[0-9+]{10,15}$/'],'password'=>'required|string|min:8',
            'role'=>['required',Rule::in(array_merge(['super_admin','pic','spv','judge','participant'],AccessRole::pluck('slug')->all()))],'competition_id'=>'nullable|exists:competitions,id','is_active'=>'boolean',
        ]);
        if(in_array($data['role'],['pic','spv'],true)&&empty($data['whatsapp']))return response()->json(['message'=>'Nomor WhatsApp aktif wajib diisi untuk PIC dan SPV.'],422);
        if(in_array($data['role'],['super_admin','pic','spv','judge','participant'],true))$data['competition_id']=null;
        return response()->json(User::create($data),201);
    }

    public function updateAccount(Request $request, User $user)
    {
        $data=$request->validate([
            'name'=>'required|string|max:120','email'=>'required|email|unique:users,email,'.$user->id,'whatsapp'=>['nullable','regex:/^[0-9+]{10,15}$/'],'password'=>'nullable|string|min:8',
            'role'=>['required',Rule::in(array_merge(['super_admin','pic','spv','judge','participant'],AccessRole::pluck('slug')->all()))],'competition_id'=>'nullable|exists:competitions,id','is_active'=>'required|boolean',
        ]);
        if($user->id===$request->user()->id&&($data['role']!=='super_admin'||!$data['is_active']))return response()->json(['message'=>'Anda tidak dapat menurunkan role atau menonaktifkan akun sendiri.'],422);
        if(in_array($data['role'],['pic','spv'],true)&&empty($data['whatsapp']))return response()->json(['message'=>'Nomor WhatsApp aktif wajib diisi untuk PIC dan SPV.'],422);
        if(in_array($data['role'],['super_admin','pic','spv','judge','participant'],true))$data['competition_id']=null;
        if(empty($data['password']))unset($data['password']);
        $user->update($data);
        return $user->fresh('competition:id,title');
    }

    public function destroyAccount(Request $request, User $user)
    {
        if($user->id===$request->user()->id)return response()->json(['message'=>'Akun yang sedang digunakan tidak dapat dihapus.'],422);
        if($user->registrations()->exists())return response()->json(['message'=>'Akun peserta yang memiliki pendaftaran tidak dapat dihapus. Nonaktifkan akun sebagai gantinya.'],422);
        $user->delete(); return response()->noContent();
    }

    public function competitionPics(Request $request, Competition $competition)
    {
        abort_unless($this->scopeCompetitions($request)->whereKey($competition->id)->exists(), 404);
        return ['competition'=>$competition->load('pics:id,name,email,whatsapp,competition_id'),'available'=>User::where('role','pic')->where('is_active',true)->orderBy('name')->get(['id','name','email','whatsapp','competition_id'])];
    }

    public function assignCompetitionPics(Request $request, Competition $competition)
    {
        abort_unless($this->scopeCompetitions($request)->whereKey($competition->id)->exists(), 404);
        $data=$request->validate(['pic_ids'=>'array|max:'.$competition->pic_slots,'pic_ids.*'=>'integer|exists:users,id']);
        $ids=collect($data['pic_ids']??[])->unique();
        $pics=User::whereIn('id',$ids)->get();
        if($pics->contains(fn($pic)=>$pic->role!=='pic'||!$pic->is_active||empty($pic->whatsapp)))return response()->json(['message'=>'Semua akun terpilih harus merupakan PIC aktif dengan nomor WhatsApp.'],422);
        User::where('competition_id',$competition->id)->where('role','pic')->whereNotIn('id',$ids)->update(['competition_id'=>null]);
        User::whereIn('id',$ids)->update(['competition_id'=>$competition->id]);
        return $competition->fresh()->load('pics:id,name,email,whatsapp,competition_id');
    }

    public function registrations(Request $request)
    {
        $request->validate([
            'competition_id'=>'nullable|integer|exists:competitions,id',
            'session_id'=>'nullable|integer|exists:competition_sessions,id',
            'page'=>'nullable|integer|min:1',
            'per_page'=>'nullable|integer|in:10,20,50,100',
        ]);
        $q=$this->scopeRegistrations($request)->with('competition:id,title,category,participation_type', 'competitionSession');
        if($request->filled('status')&&$request->status!=='all')$q->where('status',$request->status);
        if($request->filled('competition_id'))$q->where('competition_id',$request->integer('competition_id'));
        if($request->filled('session_id')) {
            abort_unless($this->scopeSessions($request)->whereKey($request->integer('session_id'))->exists(), 403);
            $q->where('competition_session_id',$request->integer('session_id'));
        }
        if($request->filled('search'))$q->where(fn($x)=>$x->where('full_name','like','%'.$request->search.'%')->orWhere('team_name','like','%'.$request->search.'%')->orWhere('ticket_code','like','%'.$request->search.'%')->orWhere('school_name','like','%'.$request->search.'%'));
        return $q->latest()->paginate($request->integer('per_page', 20))->withQueryString();
    }

    public function registrationCompetitions(Request $request)
    {
        $sessionIds = $request->user()->managesAllLocations() ? null : $this->scopeSessions($request)->pluck('competition_sessions.id');
        return $this->scopeCompetitions($request)->with(['sessions'=>function ($query) use ($sessionIds) {
            $query->where('is_active', true)->orderBy('sort_order');
            if ($sessionIds !== null) $query->whereIn('competition_sessions.id', $sessionIds);
        }])->orderBy('title')->get(['id','title']);
    }

    public function registration(Request $request, Registration $registration)
    {
        $this->authorizeRegistration($request, $registration);
        $registration->load('competition:id,title,category,participation_type,team_size,official_count,fee,submission_start_at,submission_end_at,team_update_deadline_at,document_upload_deadline_at', 'competitionSession', 'members', 'officials');
        if ($registration->competition->participation_type === 'team' && $registration->members->isEmpty()) {
            $registration->setRelation('members', collect([new RegistrationMember([
                'id'=>0, 'member_order'=>1, 'full_name'=>$registration->full_name, 'nisn'=>$registration->nisn,
                'email'=>$registration->email, 'whatsapp'=>$registration->whatsapp,
                'birth_place'=>$registration->birth_place, 'birth_date'=>$registration->birth_date,
                'grade'=>$registration->grade, 'mother_name'=>$registration->mother_name,
                'student_card_path'=>$registration->student_card_path, 'photo_path'=>$registration->photo_path,
            ])]));
        }
        if($request->user()->hasPermission('registrations.review')) {
            $registration->makeVisible('mother_name');
            $registration->members->each->makeVisible('mother_name');
        }
        return $registration;
    }

    public function destroyRegistration(Request $request, Registration $registration)
    {
        abort_unless($request->user()->role==='super_admin',403);
        $registration->delete();
        return response()->noContent();
    }

    public function updateFormat(Request $request, Competition $competition)
    {
        abort_unless($this->scopeCompetitions($request)->whereKey($competition->id)->exists(), 403);
        $data = $request->validate([
            'participation_type'=>'required|in:individual,team','team_size'=>'required|integer|min:1|max:20',
            'official_count'=>'required|integer|min:0|max:20',
        ]);
        $data['team_size'] = $data['participation_type'] === 'individual' ? 1 : $data['team_size'];
        $data['official_count'] = $data['participation_type'] === 'individual' ? 0 : $data['official_count'];
        DB::transaction(function () use ($competition, $data) {
            $competition->update($data);

            $competition->registrations()->with(['members', 'officials'])->get()->each(function (Registration $registration) use ($data) {
                if ($data['participation_type'] === 'individual') {
                    $complete = $registration->full_name
                        && $registration->nisn
                        && $registration->birth_place
                        && $registration->birth_date
                        && $registration->grade
                        && $registration->mother_name
                        && $registration->student_card_path
                        && $registration->photo_path;
                } else {
                    $complete = $registration->members->count() === $data['team_size']
                        && $registration->members->every(fn (RegistrationMember $member) =>
                            $member->full_name
                            && $member->email
                            && $member->whatsapp
                            && $member->nisn
                            && $member->birth_place
                            && $member->birth_date
                            && $member->grade
                            && $member->mother_name
                            && $member->student_card_path
                            && $member->photo_path
                        )
                        && $registration->officials->count() === $data['official_count'];
                }

                $registration->update([
                    'team_completed_at'=>$complete ? ($registration->team_completed_at ?? now()) : null,
                ]);
            });
        });

        return $competition->fresh();
    }

    public function review(Request $request, Registration $registration)
    {
        $this->authorizeRegistration($request, $registration);
        $data=$request->validate(['status'=>'required|in:approved,rejected,revision','review_note'=>'nullable|string|max:1000']);
        if(in_array($data['status'],['rejected','revision']) && empty($data['review_note'])) return response()->json(['message'=>'Catatan wajib diisi untuk penolakan atau revisi.'],422);
        if($data['status']==='approved' && (!$registration->team_completed_at || !$registration->documents_completed_at)) return response()->json(['message'=>'Data peserta dan seluruh dokumen wajib dilengkapi sebelum pendaftaran dapat diterima.'],422);
        if($data['status']==='approved' && (float) $registration->competition->fee > 0 && !$registration->payment_verified_at) return response()->json(['message'=>'Bukti pembayaran harus diperiksa dan ditandai valid sebelum peserta diterima.'],422);
        $registration->update($data+['reviewed_by'=>$request->user()->id,'reviewed_at'=>now()]); return $registration;
    }

    public function verifyPayment(Request $request, Registration $registration)
    {
        $this->authorizeRegistration($request, $registration);
        $data = $request->validate(['is_valid'=>'required|boolean']);
        if ($data['is_valid'] && !$registration->payment_proof_path) {
            return response()->json(['message'=>'Peserta belum mengunggah bukti pembayaran.'], 422);
        }
        $registration->update($data['is_valid'] ? [
            'payment_verified_at'=>now(), 'payment_verified_by'=>$request->user()->id,
        ] : [
            'payment_verified_at'=>null, 'payment_verified_by'=>null,
        ]);
        return $registration->fresh();
    }

    public function verifyMemberNisn(Request $request, RegistrationMember $registrationMember)
    {
        $this->authorizeRegistration($request, $registrationMember->registration);
        $data = $request->validate(['is_valid' => 'required|boolean']);
        $registrationMember->update($data['is_valid'] ? [
            'nisn_verified_at' => now(),
            'nisn_verified_by' => $request->user()->id,
        ] : [
            'nisn_verified_at' => null,
            'nisn_verified_by' => null,
        ]);

        return $registrationMember->fresh();
    }

    public function export(Request $request, RegistrationExcelExporter $exporter)
    {
        $request->validate([
            'competition_id'=>'nullable|integer|exists:competitions,id',
            'session_id'=>'nullable|integer|exists:competition_sessions,id',
        ]);
        $rows=$this->scopeRegistrations($request)
            ->with(['competition:id,title,category,participation_type,team_size,location','competitionSession','officials'])
            ->where('status','approved')
            ->when($request->filled('competition_id'), fn ($query) => $query->where('competition_id', $request->integer('competition_id')))
            ->when($request->filled('session_id'), fn ($query) => $query->where('competition_session_id', $request->integer('session_id')))
            ->get();
        $path=$exporter->create($rows);
        return response()->download($path,'pendaftar-tervalidasi.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }
}
