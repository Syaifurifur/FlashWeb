<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionSession;
use App\Models\CompetitionVenue;
use App\Models\CompetitionType;
use App\Models\EventEdition;
use App\Models\Registration;
use App\Models\RegistrationMember;
use App\Models\RegistrationOfficial;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PublicController extends Controller
{
    public function competitionTypes()
    {
        $editionId = EventEdition::resolveCurrent(true)->id;
        return CompetitionType::query()
            ->where('is_active', true)
            ->withCount([
                'competitions'=>fn ($query) => $query->where('event_edition_id', $editionId),
                'registrations'=>fn ($query) => $query->where('registrations.event_edition_id', $editionId),
            ])
            ->orderByDesc('registrations_count')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function registerParticipant(Request $request)
    {
        $data = $request->validate([
            'competition_id'=>'required|exists:competitions,id',
            'competition_session_id'=>'nullable|integer|exists:competition_sessions,id',
            'full_name'=>'required|string|max:120',
            'whatsapp'=>['required','regex:/^[0-9+]{10,15}$/'],
            'email'=>'required|email|max:150',
            'password'=>'required|string|min:8|confirmed',
            'consent'=>'accepted',
            'birth_place'=>'nullable|string|max:100',
            'birth_date'=>'nullable|date|before:today',
            'grade'=>'nullable|in:X,XI,XII',
            'nisn'=>['nullable','regex:/^[0-9]{10}$/'],
            'mother_name'=>'nullable|string|max:120',
            'school_name'=>'nullable|string|max:180',
            'school_city'=>'nullable|string|max:120',
            'school_address'=>'nullable|string|max:1000',
            'teacher_name'=>'nullable|string|max:120',
            'teacher_contact'=>['nullable','regex:/^[0-9+]{10,15}$/'],
            'team_name'=>'nullable|string|max:120',
            'student_card'=>'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
            'school_logo'=>'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'statement_letter'=>'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
            'delegation_letter'=>'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
            'photo'=>'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'payment_proof'=>'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
            'members'=>'nullable|array',
            'members.*.full_name'=>'required_with:members|string|max:120',
            'members.*.email'=>'required_with:members|email|max:150|distinct',
            'members.*.whatsapp'=>['required_with:members','regex:/^[0-9+]{10,15}$/','distinct'],
            'members.*.nisn'=>['required_with:members','regex:/^[0-9]{10}$/','distinct'],
            'members.*.birth_place'=>'required_with:members|string|max:100',
            'members.*.birth_date'=>'required_with:members|date|before:today',
            'members.*.grade'=>'required_with:members|in:X,XI,XII',
            'members.*.mother_name'=>'required_with:members|string|max:120',
            'member_student_cards'=>'nullable|array',
            'member_student_cards.*'=>'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
            'member_photos'=>'nullable|array',
            'member_photos.*'=>'file|mimes:jpg,jpeg,png|max:2048',
            'officials'=>'nullable|array',
            'officials.*.full_name'=>'required_with:officials|string|max:120',
            'officials.*.position'=>'required_with:officials|string|max:100',
            'officials.*.whatsapp'=>['required_with:officials','regex:/^[0-9+]{10,15}$/'],
        ]);
        $competition = Competition::where('event_edition_id', EventEdition::resolveCurrent(true)->id)->findOrFail($data['competition_id']);
        abort_unless($competition->eventEdition?->status === 'active', 422, 'Pendaftaran hanya dibuka pada tahun pelaksanaan yang sedang aktif.');
        $activeSessions = $competition->sessions()->where('is_active', true)->get();
        $selectedSession = null;
        if ($activeSessions->isNotEmpty()) {
            if (empty($data['competition_session_id'])) {
                return response()->json(['message'=>'Pilih lokasi dan jadwal lomba yang akan diikuti.','errors'=>['competition_session_id'=>['Lokasi dan jadwal wajib dipilih.']]],422);
            }
            $selectedSession = $activeSessions->firstWhere('id', (int) $data['competition_session_id']);
            if (! $selectedSession) {
                return response()->json(['message'=>'Lokasi atau jadwal yang dipilih tidak tersedia untuk lomba ini.'],422);
            }
            if ($selectedSession->quota && $selectedSession->registrations()->count() >= $selectedSession->quota) {
                return response()->json(['message'=>'Kuota pada lokasi yang dipilih telah penuh.'],422);
            }
            if ($selectedSession->team_update_deadline_at && now()->gt($selectedSession->team_update_deadline_at)) {
                return response()->json(['message'=>'Pendaftaran untuk kota yang dipilih telah ditutup.'],422);
            }
        } elseif (! empty($data['competition_session_id'])) {
            return response()->json(['message'=>'Lomba ini tidak menggunakan pilihan lokasi dan jadwal.'],422);
        }
        if (! $selectedSession && now()->gt($competition->registration_end->endOfDay())) return response()->json(['message'=>'Pendaftaran lomba ini telah ditutup.'],422);
        if (! $selectedSession && $competition->registrations()->count() >= $competition->quota) return response()->json(['message'=>'Kuota lomba telah penuh.'],422);
        if (! empty($data['nisn'])) {
            $nisns = collect([$data['nisn']])->merge(collect($data['members'] ?? [])->pluck('nisn'));
            if ($competition->registrations()->whereIn('nisn',$nisns)->exists()
                || RegistrationMember::where('competition_id',$competition->id)->whereIn('nisn',$nisns)->exists()) {
                return response()->json(['message'=>'NISN sudah terdaftar pada lomba ini.'],422);
            }
        }
        $existingUser = User::where('email',$data['email'])->first();
        if ($existingUser && ($existingUser->role !== 'participant' || !Hash::check($data['password'],$existingUser->password))) {
            return response()->json(['message'=>'Email sudah digunakan atau password akun peserta tidak sesuai.'],422);
        }

        return DB::transaction(function () use ($request,$data,$competition,$existingUser,$selectedSession) {
            if ($selectedSession) {
                $lockedSession = CompetitionSession::whereKey($selectedSession->id)->lockForUpdate()->firstOrFail();
                if ($lockedSession->quota && $lockedSession->registrations()->count() >= $lockedSession->quota) {
                    return response()->json(['message'=>'Kuota pada lokasi yang dipilih telah penuh.'],422);
                }
            }
            $user = $existingUser ?: User::create(['name'=>$data['full_name'],'email'=>$data['email'],'password'=>$data['password'],'role'=>'participant']);
            if ($user->registrations()->where('competition_id',$competition->id)->exists()) return response()->json(['message'=>'Akun ini sudah mendaftar pada lomba tersebut.'],422);
            $registrationData = collect($data)->only([
                'competition_id','competition_session_id','full_name','email','whatsapp','birth_place','birth_date','grade','nisn','mother_name',
                'school_name','school_city','school_address','teacher_name','teacher_contact','team_name','consent',
            ])->all() + [
                'competition_id'=>$competition->id,
                'competition_session_id'=>$selectedSession?->id,
                'user_id'=>$user->id,
                'ticket_code'=>'BSIFLASH-'.strtoupper(Str::random(8)),
                'consent'=>true,
                'status'=>'pending',
            ];
            foreach (['student_card','school_logo','statement_letter','delegation_letter','photo','payment_proof'] as $file) {
                if ($request->hasFile($file)) $registrationData[$file.'_path'] = $request->file($file)->store('registrations/'.$competition->id,'public');
            }
            $registration=Registration::create($registrationData);
            if ($competition->participation_type === 'team') {
                RegistrationMember::create([
                    'registration_id'=>$registration->id, 'competition_id'=>$competition->id, 'member_order'=>1,
                    'email'=>$registration->email, 'whatsapp'=>$registration->whatsapp,
                    'full_name'=>$registration->full_name,
                    'nisn'=>$registration->nisn, 'birth_place'=>$registration->birth_place,
                    'birth_date'=>$registration->birth_date, 'grade'=>$registration->grade,
                    'mother_name'=>$registration->mother_name, 'student_card_path'=>$registration->student_card_path,
                    'photo_path'=>$registration->photo_path,
                ]);
                foreach ($data['members'] ?? [] as $index => $member) {
                    RegistrationMember::create($member + [
                        'registration_id'=>$registration->id,
                        'competition_id'=>$competition->id,
                        'member_order'=>$index + 2,
                        'student_card_path'=>$request->hasFile("member_student_cards.$index") ? $request->file("member_student_cards.$index")->store('registrations/'.$competition->id.'/members','public') : null,
                        'photo_path'=>$request->hasFile("member_photos.$index") ? $request->file("member_photos.$index")->store('registrations/'.$competition->id.'/members','public') : null,
                    ]);
                }
                foreach ($data['officials'] ?? [] as $index => $official) {
                    RegistrationOfficial::create($official + ['registration_id'=>$registration->id,'official_order'=>$index + 1]);
                }
            }
            $registration->refresh()->load('members');
            $registrationFee = $selectedSession?->fee ?? $competition->fee;
            $sharedDocumentsComplete = $registration->school_logo_path && $registration->statement_letter_path
                && $registration->delegation_letter_path && ($registrationFee <= 0 || $registration->payment_proof_path);
            $participantDocumentsComplete = $competition->participation_type === 'team'
                ? $registration->members->count() === $competition->team_size && $registration->members->every(fn ($member) => $member->student_card_path && $member->photo_path)
                : $registration->student_card_path && $registration->photo_path;
            $completion = [];
            if (! empty($data['nisn']) && $participantDocumentsComplete) $completion['team_completed_at'] = now();
            if ($sharedDocumentsComplete) $completion['documents_completed_at'] = now();
            if ($completion) $registration->update($completion);
            return response()->json([
                'message'=>'Pendaftaran awal berhasil. Masuk ke dashboard untuk melengkapi data dan dokumen.',
                'ticket_code'=>$registration->ticket_code,
                'email'=>$user->email,
            ],201);
        });
    }

    public function competitions(Request $request)
    {
        $edition = EventEdition::resolveCurrent(true);
        $query = Competition::where('event_edition_id', $edition->id)->with(['competitionType:id,name,slug,category_group','sessions'=>fn ($q) => $q->where('is_active', true)->with(['venueRecord:id,slug,city,name,field_photo_url,pic_user_id,supervisor_user_id','pic:id,name,whatsapp','supervisor:id,name,whatsapp','pics:id,name,whatsapp','supervisors:id,name,whatsapp'])->withCount('registrations')])->withCount('registrations');
        if ($request->boolean('active')) $query->where(fn ($builder) => $builder->whereDoesntHave('sessions')->orWhereHas('sessions', fn ($session) => $session->where('is_active', true)));
        if ($request->filled('competition_type_id')) $query->where('competition_type_id', $request->integer('competition_type_id'));
        if ($request->filled('category') && $request->category !== 'Semua') $query->where('category', $request->category);
        if ($request->filled('search')) $query->where(fn($q) => $q->where('title', 'like', '%'.$request->search.'%')->orWhere('short_description', 'like', '%'.$request->search.'%'));
        if ($request->status === 'Buka') $query->whereDate('registration_end', '>=', now());
        if ($request->status === 'Tutup') $query->whereDate('registration_end', '<', now());
        return $query->orderByDesc('is_featured')->orderBy('registration_end')->get();
    }

    public function venues()
    {
        return CompetitionVenue::query()
            ->where('event_edition_id', EventEdition::resolveCurrent(true)->id)
            ->where('is_active', true)
            ->with(['pic:id,name,whatsapp','supervisor:id,name,whatsapp'])
            ->withCount(['sessions'=>fn ($query) => $query->where('is_active', true)])
            ->orderBy('activity_start_date')
            ->orderBy('city')
            ->get();
    }

    public function venue(string $slug)
    {
        return CompetitionVenue::query()
            ->where('event_edition_id', EventEdition::resolveCurrent(true)->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with(['pic:id,name,whatsapp','supervisor:id,name,whatsapp'])
            ->with(['sessions'=>fn ($query) => $query
                ->where('is_active', true)
                ->withCount('registrations')
                ->with(['pic:id,name,whatsapp','supervisor:id,name,whatsapp','pics:id,name,whatsapp','supervisors:id,name,whatsapp','competition'=>fn ($competition) => $competition->with('competitionType:id,name,slug,category_group')->withCount('registrations')])])
            ->firstOrFail();
    }

    public function competition(string $slug)
    {
        return Competition::withCount('registrations')->with([
            'competitionType:id,name,slug,category_group',
            'pics'=>fn($q)=>$q->where('role','pic')->where('is_active',true)->whereNotNull('whatsapp')->select('id','competition_id','name','whatsapp'),
            'sessions'=>fn ($q) => $q->where('is_active', true)->with(['venueRecord:id,slug,city,name,field_photo_url,pic_user_id,supervisor_user_id','venueRecord.pic:id,name,whatsapp','venueRecord.supervisor:id,name,whatsapp','pic:id,name,whatsapp','supervisor:id,name,whatsapp','pics:id,name,whatsapp','supervisors:id,name,whatsapp'])->withCount('registrations'),
        ])->where('event_edition_id', EventEdition::resolveCurrent(true)->id)->where('slug', $slug)->firstOrFail();
    }

    public function register(Request $request)
    {
        $request->validate(['competition_id' => 'required|exists:competitions,id']);
        $competition = Competition::findOrFail($request->competition_id);
        $isTeam = $competition->participation_type === 'team';
        $rules = [
            'competition_id' => 'required|exists:competitions,id',
            'whatsapp' => ['required','regex:/^[0-9+]{10,15}$/'], 'email' => 'required|email|max:150',
            'school_name' => 'required|string|max:180', 'school_city'=>'required|string|max:120',
            'school_address'=>'required|string|max:1000',
            'teacher_name' => 'required|string|max:120', 'teacher_contact' => ['required','regex:/^[0-9+]{10,15}$/'],
            'team_name' => ($isTeam ? 'required' : 'nullable').'|string|max:120',
            'participant_category' => 'nullable|string|max:80', 'consent' => 'accepted',
            'delegation_letter' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ];
        if ($isTeam) {
            $rules += [
                'members' => ['required','array','size:'.$competition->team_size],
                'members.*.full_name' => 'required|string|max:120',
                'members.*.nisn' => ['required','regex:/^[0-9]{10}$/','distinct'],
                'members.*.birth_place' => 'required|string|max:100',
                'members.*.birth_date' => 'required|date|before:today',
                'members.*.grade' => 'required|in:X,XI,XII',
                'members.*.mother_name' => 'required|string|max:120',
                'member_student_cards' => ['required','array','size:'.$competition->team_size],
                'member_student_cards.*' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'member_photos' => ['required','array','size:'.$competition->team_size],
                'member_photos.*' => 'required|file|mimes:jpg,jpeg,png|max:2048',
            ];
        } else {
            $rules += [
                'full_name' => 'required|string|max:120', 'birth_place' => 'required|string|max:100',
                'birth_date' => 'required|date|before:today', 'grade' => 'required|in:X,XI,XII',
                'nisn' => ['required','regex:/^[0-9]{10}$/'], 'mother_name' => 'required|string|max:120',
                'student_card' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'photo' => 'required|file|mimes:jpg,jpeg,png|max:2048',
            ];
        }
        $data = $request->validate($rules);
        if ($competition->registration_end->isPast()) return response()->json(['message' => 'Pendaftaran lomba ini telah ditutup.'], 422);
        if ($competition->registrations()->count() >= $competition->quota) return response()->json(['message' => 'Kuota lomba telah penuh.'], 422);
        $nisns = $isTeam ? collect($data['members'])->pluck('nisn') : collect([$data['nisn']]);
        $alreadyRegistered = $competition->registrations()->whereIn('nisn', $nisns)->exists()
            || RegistrationMember::where('competition_id', $competition->id)->whereIn('nisn', $nisns)->exists();
        if ($alreadyRegistered) return response()->json(['message' => 'Salah satu NISN sudah terdaftar pada lomba ini.'], 422);

        return DB::transaction(function () use ($request, $data, $competition, $isTeam) {
            if ($isTeam) {
                $leader = $data['members'][0];
                $data = array_merge($data, $leader);
                $data['student_card_path'] = $request->file('member_student_cards.0')->store('registrations/'.$competition->id, 'public');
                $data['photo_path'] = $request->file('member_photos.0')->store('registrations/'.$competition->id, 'public');
            } else {
                foreach (['student_card','photo'] as $file) {
                    $data[$file.'_path'] = $request->file($file)->store('registrations/'.$competition->id, 'public');
                    unset($data[$file]);
                }
            }
            foreach (['delegation_letter','payment_proof'] as $file) {
                if ($request->hasFile($file)) $data[$file.'_path'] = $request->file($file)->store('registrations/'.$competition->id, 'public');
                unset($data[$file]);
            }
            $members = $data['members'] ?? [];
            unset($data['members'], $data['member_student_cards'], $data['member_photos']);
            $data['ticket_code'] = 'BSIFLASH-'.strtoupper(Str::random(8));
            $registration = Registration::create($data);
            if ($isTeam) {
                foreach ($members as $index => $member) {
                    RegistrationMember::create($member + [
                        'registration_id' => $registration->id, 'competition_id' => $competition->id,
                        'member_order' => $index + 1,
                        'student_card_path' => $request->file("member_student_cards.$index")->store('registrations/'.$competition->id.'/members', 'public'),
                        'photo_path' => $request->file("member_photos.$index")->store('registrations/'.$competition->id.'/members', 'public'),
                    ]);
                }
            }
            return response()->json(['message' => 'Pendaftaran berhasil dikirim.', 'ticket_code' => $registration->ticket_code], 201);
        });
    }

    public function track(Request $request, string $code)
    {
        $request->validate(['email' => 'required|email']);
        $registration = Registration::with('competition:id,title,slug,event_date', 'competitionSession')
            ->where('ticket_code', strtoupper($code))->where('email', $request->email)->firstOrFail();
        return $registration->makeHidden(['nisn','whatsapp','teacher_contact','student_card_path','delegation_letter_path','photo_path','payment_proof_path']);
    }
}
