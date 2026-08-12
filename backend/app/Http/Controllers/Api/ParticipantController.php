<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\RegistrationMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ParticipantController extends Controller
{
    private array $relations = [
        'competition:id,title,slug,category,event_date,participation_type,team_size,official_count,fee,bank_name,bank_account_number,bank_account_holder,payment_note,submission_start_at,submission_end_at,team_update_deadline_at,document_upload_deadline_at,downloadable_documents',
        'competitionSession',
        'members',
        'officials',
    ];

    private function revealOwnedData(Registration $registration): Registration
    {
        $registration->makeVisible('mother_name');
        $registration->members->each->makeVisible('mother_name');
        return $registration;
    }

    private function syncTeamCompletion(Registration $registration): bool
    {
        $registration->loadMissing('competition');
        $members = $registration->members()->get();
        $complete = $members->count() === (int) $registration->competition->team_size
            && $members->every(fn (RegistrationMember $member) =>
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
            && $registration->officials()->count() === (int) $registration->competition->official_count;

        $registration->update([
            'team_completed_at'=>$complete ? ($registration->team_completed_at ?? now()) : null,
        ]);

        return $complete;
    }

    public function index(Request $request)
    {
        return $request->user()->registrations()->with($this->relations)->latest()->get()
            ->each(fn (Registration $registration) => $this->revealOwnedData($registration));
    }

    public function show(Request $request, Registration $registration)
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        return $this->revealOwnedData($registration->load($this->relations));
    }

    public function submitWork(Request $request, Registration $registration)
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        $registration->load('competition', 'competitionSession');
        $competition = $registration->competition;
        $schedule = $registration->competitionSession;
        if ($competition->category === 'Sport Competition') {
            return response()->json(['message'=>'Lomba olahraga tidak menerima pengumpulan karya.'], 422);
        }
        $submissionStart = $schedule?->submission_start_at ?? $competition->submission_start_at;
        $submissionEnd = $schedule?->submission_end_at ?? $competition->submission_end_at;
        if (! $submissionStart || ! $submissionEnd) {
            return response()->json(['message'=>'Jadwal pengumpulan karya belum ditetapkan.'], 422);
        }
        if (now()->lt($submissionStart) || now()->gt($submissionEnd)) {
            return response()->json(['message'=>'Pengumpulan karya sedang tidak dibuka.'], 422);
        }
        $data = $request->validate(['work_submission_url'=>'required|url:http,https|max:2000']);
        $registration->update([
            'work_submission_url'=>$data['work_submission_url'],
            'work_submitted_at'=>now(),
        ]);

        return $registration->fresh()->load($this->relations);
    }

    public function updateTeam(Request $request, Registration $registration)
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        $registration->load('competition', 'competitionSession', 'members', 'officials');
        $competition = $registration->competition;
        $completionDeadline = $registration->competitionSession?->team_update_deadline_at ?? $competition->team_update_deadline_at;
        if (! $completionDeadline) {
            return response()->json(['message'=>'Batas waktu pembaruan data belum ditetapkan PIC.'], 422);
        }
        if (now()->gt($completionDeadline)) {
            return response()->json(['message'=>'Batas waktu pembaruan data tim telah berakhir.'], 403);
        }

        $isTeam = $competition->participation_type === 'team';
        $rules = [
            'school_name'=>'required|string|max:180',
            'school_city'=>'required|string|max:120',
            'school_address'=>'required|string|max:1000',
            'teacher_name'=>'required|string|max:120',
            'teacher_contact'=>['required','regex:/^[0-9+]{10,15}$/'],
            'team_name'=>($isTeam?'required':'nullable').'|string|max:120',
        ];

        if ($isTeam) {
            $rules += [
                'members'=>['required','array','size:'.$competition->team_size],
                'members.*.full_name'=>'required|string|max:120',
                'members.*.email'=>'required|email|max:150|distinct',
                'members.*.whatsapp'=>['required','regex:/^[0-9+]{10,15}$/','distinct'],
                'members.*.nisn'=>['required','regex:/^[0-9]{10}$/','distinct'],
                'members.*.birth_place'=>'required|string|max:100',
                'members.*.birth_date'=>'required|date|before:today',
                'members.*.grade'=>'required|in:X,XI,XII',
                'members.*.mother_name'=>'required|string|max:120',
                'member_student_cards'=>'nullable|array',
                'member_photos'=>'nullable|array',
                'officials'=>[$competition->official_count ? 'required' : 'nullable','array','size:'.$competition->official_count],
                'officials.*.full_name'=>'required|string|max:120',
                'officials.*.position'=>'required|string|max:100',
                'officials.*.whatsapp'=>['required','regex:/^[0-9+]{10,15}$/'],
            ];
        } else {
            $rules += [
                'full_name'=>'required|string|max:120',
                'whatsapp'=>['required','regex:/^[0-9+]{10,15}$/'],
                'nisn'=>['required','regex:/^[0-9]{10}$/',Rule::unique('registrations')->where('competition_id',$registration->competition_id)->ignore($registration->id)],
                'birth_place'=>'required|string|max:100',
                'birth_date'=>'required|date|before:today',
                'grade'=>'required|in:X,XI,XII',
                'mother_name'=>'required|string|max:120',
                'student_card'=>[Rule::requiredIf(! $registration->student_card_path),'nullable','file','mimes:jpg,jpeg,png,pdf,doc,docx','max:2048'],
                'photo'=>[Rule::requiredIf(! $registration->photo_path),'nullable','file','mimes:jpg,jpeg,png','max:2048'],
            ];
        }

        if ($isTeam) {
            foreach (range(0, $competition->team_size - 1) as $index) {
                $rules["member_student_cards.$index"] = ['nullable','file','mimes:jpg,jpeg,png,pdf,doc,docx','max:2048'];
                $rules["member_photos.$index"] = ['nullable','file','mimes:jpg,jpeg,png','max:2048'];
            }
        }

        $data = $request->validate($rules);
        $nisns = $isTeam ? collect($data['members'])->pluck('nisn') : collect([$data['nisn']]);
        $duplicateNisn = Registration::where('competition_id',$registration->competition_id)->whereKeyNot($registration->id)->whereIn('nisn',$nisns)->exists()
            || RegistrationMember::where('competition_id',$registration->competition_id)->where('registration_id','!=',$registration->id)->whereIn('nisn',$nisns)->exists();
        if ($duplicateNisn) return response()->json(['message'=>'Salah satu NISN sudah terdaftar pada lomba ini.'], 422);

        DB::transaction(function () use ($request, $registration, $data, $isTeam, $competition) {
            $shared = collect($data)->only(['school_name','school_city','school_address','teacher_name','teacher_contact','team_name'])->all();
            if ($isTeam) {
                foreach ($data['members'] as $index => $memberData) {
                    $member = $registration->members->firstWhere('member_order', $index + 1) ?? new RegistrationMember([
                        'registration_id'=>$registration->id,
                        'competition_id'=>$registration->competition_id,
                        'member_order'=>$index + 1,
                    ]);
                    if ($request->hasFile("member_student_cards.$index")) $memberData['student_card_path'] = $request->file("member_student_cards.$index")->store('registrations/'.$registration->competition_id.'/members','public');
                    if ($request->hasFile("member_photos.$index")) $memberData['photo_path'] = $request->file("member_photos.$index")->store('registrations/'.$registration->competition_id.'/members','public');
                    $member->fill($memberData + ['nisn_verified_at'=>null,'nisn_verified_by'=>null])->save();
                }
                $registration->members()->where('member_order','>',$competition->team_size)->delete();
                $leader = $registration->members()->where('member_order',1)->firstOrFail();
                $shared += collect($leader->getAttributes())->only(['full_name','email','whatsapp','birth_place','birth_date','grade','nisn'])->all();
                $shared['mother_name'] = $leader->mother_name;
                $shared['student_card_path'] = $leader->student_card_path;
                $shared['photo_path'] = $leader->photo_path;
                foreach ($data['officials'] ?? [] as $index => $official) {
                    $registration->officials()->updateOrCreate(['official_order'=>$index + 1], $official);
                }
                $registration->officials()->where('official_order','>',$competition->official_count)->delete();
            } else {
                $shared += collect($data)->only(['full_name','whatsapp','birth_place','birth_date','grade','nisn','mother_name'])->all();
                foreach (['student_card','photo'] as $file) {
                    if ($request->hasFile($file)) $shared[$file.'_path'] = $request->file($file)->store('registrations/'.$registration->competition_id,'public');
                }
            }
            $registration->update($shared + [
                'team_completed_at'=>$isTeam ? null : now(),
                'status'=>'pending',
                'review_note'=>null,
                'reviewed_by'=>null,
                'reviewed_at'=>null,
            ]);
            $request->user()->update(['name'=>$registration->fresh()->full_name]);
        });

        if ($isTeam) $this->syncTeamCompletion($registration);

        return response()->json([
            'message'=>$isTeam && ! $registration->fresh()->team_completed_at
                ? 'Data tim berhasil disimpan. Lanjutkan upload dokumen setiap anggota.'
                : 'Data peserta dan tim berhasil disimpan.',
            'registration'=>$this->revealOwnedData($registration->fresh($this->relations)),
        ]);
    }

    public function uploadMemberDocuments(Request $request, Registration $registration, RegistrationMember $registrationMember)
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        abort_unless($registrationMember->registration_id === $registration->id, 404);
        $registration->load('competition', 'competitionSession');
        abort_unless($registration->competition->participation_type === 'team', 422, 'Upload per anggota hanya tersedia untuk pendaftaran tim.');

        $completionDeadline = $registration->competitionSession?->team_update_deadline_at
            ?? $registration->competition->team_update_deadline_at;
        if (! $completionDeadline) {
            return response()->json(['message'=>'Batas waktu pembaruan data belum ditetapkan PIC.'], 422);
        }
        if (now()->gt($completionDeadline)) {
            return response()->json(['message'=>'Batas waktu pembaruan data tim telah berakhir.'], 403);
        }

        $request->validate([
            'student_card'=>[Rule::requiredIf(! $registrationMember->student_card_path),'nullable','file','mimes:jpg,jpeg,png,pdf,doc,docx','max:2048'],
            'photo'=>[Rule::requiredIf(! $registrationMember->photo_path),'nullable','file','mimes:jpg,jpeg,png','max:2048'],
        ]);

        $updates = [];
        if ($request->hasFile('student_card')) {
            $updates['student_card_path'] = $request->file('student_card')->store('registrations/'.$registration->competition_id.'/members','public');
            $updates['nisn_verified_at'] = null;
            $updates['nisn_verified_by'] = null;
        }
        if ($request->hasFile('photo')) {
            $updates['photo_path'] = $request->file('photo')->store('registrations/'.$registration->competition_id.'/members','public');
        }
        if ($updates) $registrationMember->update($updates);

        if ($registrationMember->member_order === 1) {
            $registration->update([
                'student_card_path'=>$registrationMember->fresh()->student_card_path,
                'photo_path'=>$registrationMember->fresh()->photo_path,
            ]);
        }

        $complete = $this->syncTeamCompletion($registration);
        $uploadedMembers = $registration->members()->whereNotNull('student_card_path')->whereNotNull('photo_path')->count();

        return response()->json([
            'message'=>$complete ? 'Seluruh dokumen anggota berhasil diunggah.' : 'Dokumen anggota berhasil diunggah.',
            'uploaded_members'=>$uploadedMembers,
            'total_members'=>(int) $registration->competition->team_size,
            'team_completed'=>$complete,
            'registration'=>$this->revealOwnedData($registration->fresh($this->relations)),
        ]);
    }

    public function uploadDocuments(Request $request, Registration $registration)
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        $registration->load('competition', 'competitionSession', 'members', 'officials');
        $competition = $registration->competition;
        $documentDeadline = $registration->competitionSession?->team_update_deadline_at ?? $competition->document_upload_deadline_at;
        if (! $documentDeadline) {
            return response()->json(['message'=>'Batas waktu upload dokumen belum ditetapkan PIC.'], 422);
        }
        if (now()->gt($documentDeadline)) {
            return response()->json(['message'=>'Batas waktu upload dokumen telah berakhir.'], 403);
        }
        $documentTypes = 'jpg,jpeg,png,pdf,doc,docx';
        $rules = [
            'school_logo'=>[Rule::requiredIf(! $registration->school_logo_path),'nullable','file','mimes:jpg,jpeg,png','max:2048'],
            'statement_letter'=>[Rule::requiredIf(! $registration->statement_letter_path),'nullable','file','mimes:'.$documentTypes,'max:2048'],
            'school_recommendation_letter'=>[Rule::requiredIf(! $registration->delegation_letter_path),'nullable','file','mimes:'.$documentTypes,'max:2048'],
            'delegation_letter'=>'nullable|file|mimes:'.$documentTypes.'|max:2048',
            'payment_proof'=>[Rule::requiredIf(($registration->competitionSession?->fee ?? $competition->fee) > 0 && ! $registration->payment_proof_path),'nullable','file','mimes:'.$documentTypes,'max:2048'],
        ];
        $request->validate($rules);

        DB::transaction(function () use ($request, $registration) {
            $updates = [];
            foreach (['school_logo','statement_letter','payment_proof'] as $file) {
                if ($request->hasFile($file)) $updates[$file.'_path'] = $request->file($file)->store('registrations/'.$registration->competition_id, 'public');
            }
            $recommendationFile = $request->file('school_recommendation_letter') ?: $request->file('delegation_letter');
            if ($recommendationFile) $updates['delegation_letter_path'] = $recommendationFile->store('registrations/'.$registration->competition_id, 'public');
            if ($request->hasFile('payment_proof')) {
                $updates['payment_verified_at'] = null;
                $updates['payment_verified_by'] = null;
            }
            $registration->update($updates + [
                'documents_completed_at'=>now(),
                'status'=>'pending',
                'review_note'=>null,
                'reviewed_by'=>null,
                'reviewed_at'=>null,
            ]);
        });

        return response()->json([
            'message'=>'Seluruh dokumen berhasil disimpan.',
            'registration'=>$this->revealOwnedData($registration->fresh($this->relations)),
        ]);
    }

    public function update(Request $request, Registration $registration)
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        if ($registration->status !== 'revision') {
            return response()->json(['message'=>'Data hanya dapat diubah ketika Super Admin atau tim registrasi meminta revisi.'], 403);
        }

        $registration->load('competition', 'members', 'officials');
        $isTeam = $registration->competition->participation_type === 'team';
        $rules = [
            'school_name'=>'required|string|max:180', 'school_city'=>'nullable|string|max:120',
            'school_address'=>'nullable|string|max:1000', 'teacher_name'=>'required|string|max:120',
            'teacher_contact'=>['required','regex:/^[0-9+]{10,15}$/'],
            'team_name'=>($isTeam?'required':'nullable').'|string|max:120',
            'delegation_letter'=>'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'payment_proof'=>'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ];

        if ($isTeam) {
            $teamSize = $registration->competition->team_size;
            $officialCount = $registration->competition->official_count;
            $rules += [
                'members'=>['required','array','size:'.$teamSize],
                'members.*.full_name'=>'required|string|max:120', 'members.*.email'=>'required|email|max:150|distinct',
                'members.*.whatsapp'=>['required','regex:/^[0-9+]{10,15}$/','distinct'],
                'members.*.nisn'=>['required','regex:/^[0-9]{10}$/','distinct'],
                'members.*.birth_place'=>'required|string|max:100', 'members.*.birth_date'=>'required|date|before:today',
                'members.*.grade'=>'required|in:X,XI,XII', 'members.*.mother_name'=>'required|string|max:120',
                'member_student_cards'=>'nullable|array', 'member_student_cards.*'=>'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'member_photos'=>'nullable|array', 'member_photos.*'=>'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'officials'=>[$officialCount ? 'required' : 'nullable','array','size:'.$officialCount],
                'officials.*.full_name'=>'required|string|max:120', 'officials.*.position'=>'required|string|max:100',
                'officials.*.whatsapp'=>['required','regex:/^[0-9+]{10,15}$/'],
            ];
        } else {
            $rules += [
                'full_name'=>'required|string|max:120', 'whatsapp'=>['required','regex:/^[0-9+]{10,15}$/'],
                'birth_place'=>'required|string|max:100', 'birth_date'=>'required|date|before:today', 'grade'=>'required|in:X,XI,XII',
                'nisn'=>['required','regex:/^[0-9]{10}$/',Rule::unique('registrations')->where('competition_id',$registration->competition_id)->ignore($registration->id)],
                'mother_name'=>'nullable|string|max:120', 'student_card'=>'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'photo'=>'nullable|file|mimes:jpg,jpeg,png|max:2048',
            ];
        }

        $data = $request->validate($rules);
        $nisns = $isTeam ? collect($data['members'])->pluck('nisn') : collect([$data['nisn']]);
        $duplicateNisn = Registration::where('competition_id',$registration->competition_id)->whereKeyNot($registration->id)->whereIn('nisn',$nisns)->exists()
            || RegistrationMember::where('competition_id',$registration->competition_id)->where('registration_id','!=',$registration->id)->whereIn('nisn',$nisns)->exists();
        if ($duplicateNisn) return response()->json(['message'=>'Salah satu NISN sudah terdaftar pada lomba ini.'], 422);

        DB::transaction(function () use ($request, $registration, $data, $isTeam) {
            $shared = collect($data)->only(['school_name','school_city','school_address','teacher_name','teacher_contact','team_name'])->all();
            foreach (['delegation_letter','payment_proof'] as $file) {
                if ($request->hasFile($file)) $shared[$file.'_path'] = $request->file($file)->store('registrations/'.$registration->competition_id, 'public');
            }
            if ($request->hasFile('payment_proof')) {
                $shared['payment_verified_at'] = null;
                $shared['payment_verified_by'] = null;
            }

            if ($isTeam) {
                foreach ($data['members'] as $index => $memberData) {
                    $order = $index + 1;
                    $member = $registration->members->firstWhere('member_order', $order) ?? new RegistrationMember([
                        'registration_id'=>$registration->id, 'competition_id'=>$registration->competition_id, 'member_order'=>$order,
                    ]);
                    if ($request->hasFile("member_student_cards.$index")) {
                        $memberData['student_card_path'] = $request->file("member_student_cards.$index")->store('registrations/'.$registration->competition_id.'/members', 'public');
                    } elseif (!$member->exists && $order === 1) $memberData['student_card_path'] = $registration->student_card_path;
                    if ($request->hasFile("member_photos.$index")) {
                        $memberData['photo_path'] = $request->file("member_photos.$index")->store('registrations/'.$registration->competition_id.'/members', 'public');
                    } elseif (!$member->exists && $order === 1) $memberData['photo_path'] = $registration->photo_path;
                    $member->fill($memberData + ['nisn_verified_at'=>null,'nisn_verified_by'=>null])->save();
                }

                $leader = $registration->members()->where('member_order',1)->firstOrFail();
                $shared += collect($leader->toArray())->only(['full_name','email','whatsapp','birth_place','birth_date','grade','nisn','mother_name','student_card_path','photo_path'])->all();
                $shared['mother_name'] = $leader->mother_name;
                foreach ($data['officials'] ?? [] as $index => $official) {
                    $registration->officials()->updateOrCreate(['official_order'=>$index + 1], $official);
                }
            } else {
                $shared += collect($data)->only(['full_name','whatsapp','birth_place','birth_date','grade','nisn','mother_name'])->all();
                foreach (['student_card','photo'] as $file) {
                    if ($request->hasFile($file)) $shared[$file.'_path'] = $request->file($file)->store('registrations/'.$registration->competition_id, 'public');
                }
            }

            $registration->update($shared + ['status'=>'pending','review_note'=>null,'reviewed_by'=>null,'reviewed_at'=>null]);
            $request->user()->update(['name'=>$registration->full_name]);
        });

        $fresh = $this->revealOwnedData($registration->fresh($this->relations));
        return response()->json(['message'=>'Perubahan tim berhasil dikirim dan menunggu verifikasi ulang.','registration'=>$fresh]);
    }
}
