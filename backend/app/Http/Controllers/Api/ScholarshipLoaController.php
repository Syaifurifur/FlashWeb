<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\CompetitionSession;
use App\Models\EventEdition;
use App\Models\Registration;
use App\Models\ScholarshipLoaIssuance;
use App\Models\ScholarshipLoaTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ScholarshipLoaController extends Controller
{
    private const DEFAULT_BODY = "Dengan hormat,\n\nBerdasarkan hasil resmi {{nama_lomba}} yang diselenggarakan pada {{kota_pelaksanaan}}, kami menyatakan bahwa {{nama_peserta}} sebagai {{peran_penerima}} dari {{nama_tim}}, {{sekolah}}, meraih {{peringkat}}.\n\nSebagai bentuk apresiasi atas prestasi tersebut, yang bersangkutan berhak memperoleh {{nama_beasiswa}} sebesar {{besaran_beasiswa}} sesuai syarat dan ketentuan yang berlaku. Letter of Acceptance ini diterbitkan khusus atas nama {{nama_peserta}} dan dapat digunakan sebagai bukti resmi penerimaan beasiswa.\n\nDemikian surat ini diterbitkan untuk dipergunakan sebagaimana mestinya.";

    private const DEFAULT_AWARDS = [1=>'100%', 2=>'75%', 3=>'50%', 4=>'25%'];

    private function edition(): EventEdition
    {
        return EventEdition::resolveCurrent();
    }

    private function competitionAndSession(Request $request): array
    {
        $competition = Competition::where('event_edition_id', $this->edition()->id)->findOrFail($request->integer('competition_id'));
        $session = $request->filled('competition_session_id')
            ? $competition->sessions()->findOrFail($request->integer('competition_session_id'))
            : null;
        if ($competition->sessions()->exists()) abort_unless($session, 422, 'Pilih kota pelaksanaan terlebih dahulu.');
        return [$competition, $session];
    }

    private function scopes(): Collection
    {
        return Competition::where('event_edition_id', $this->edition()->id)
            ->with('sessions:id,competition_id,city,venue,sort_order')->orderBy('title')->get()
            ->flatMap(function (Competition $competition) {
                if ($competition->sessions->isEmpty()) return [[
                    'competition_id'=>$competition->id, 'competition_session_id'=>null,
                    'label'=>$competition->title, 'competition_title'=>$competition->title,
                    'city'=>null, 'venue'=>null,
                ]];
                return $competition->sessions->map(fn (CompetitionSession $session) => [
                    'competition_id'=>$competition->id, 'competition_session_id'=>$session->id,
                    'label'=>$competition->title.' · '.$session->city,
                    'competition_title'=>$competition->title, 'city'=>$session->city, 'venue'=>$session->venue,
                ]);
            })->values();
    }

    private function effectiveResults(Competition $competition, ?CompetitionSession $session): Collection
    {
        $priority = ['manual'=>0, 'tournament'=>1, 'judging'=>2];
        return CompetitionResult::where('competition_id', $competition->id)
            ->where('competition_session_id', $session?->id)->whereBetween('rank', [1, 4])
            ->with([
                'registration.members:id,registration_id,member_order,full_name,nisn,grade',
                'registration.officials:id,registration_id,official_order,full_name,position',
                'scholarshipLoaIssuances:id,competition_result_id,recipient_key,document_number,snapshot',
            ])
            ->get()->sortBy(fn (CompetitionResult $result) => ($priority[$result->source] ?? 9) * 10 + $result->rank)
            ->groupBy('rank')->map->first()->sortKeys()->values()
            ->each(function (CompetitionResult $result) {
                $result->setAttribute('loa_recipient_count', $this->recipients($result->registration)->count());
            });
    }

    private function templatePayload(ScholarshipLoaTemplate $template): array
    {
        return [...$template->toArray(), 'issuances_count'=>$template->issuances_count ?? $template->issuances()->count()];
    }

    public function index(Request $request)
    {
        $scopes = $this->scopes();
        $scope = $request->filled('competition_id')
            ? $scopes->first(fn ($item) => (int)$item['competition_id'] === $request->integer('competition_id')
                && (int)($item['competition_session_id'] ?? 0) === $request->integer('competition_session_id'))
            : $scopes->first();
        $templates = ScholarshipLoaTemplate::where('event_edition_id', $this->edition()->id)
            ->withCount('issuances')->latest('is_active')->latest()->get()->map(fn ($template) => $this->templatePayload($template));

        if (! $scope) return ['templates'=>$templates, 'scopes'=>$scopes, 'scope'=>null, 'results'=>[], 'candidates'=>[]];
        $request->merge(['competition_id'=>$scope['competition_id'], 'competition_session_id'=>$scope['competition_session_id']]);
        [$competition, $session] = $this->competitionAndSession($request);
        $results = $this->effectiveResults($competition, $session);
        $candidates = Registration::where('competition_id', $competition->id)->where('competition_session_id', $session?->id)
            ->where('status', 'approved')->orderByRaw('COALESCE(team_name, full_name)')
            ->get(['id','ticket_code','full_name','team_name','school_name','school_city']);

        return [
            'templates'=>$templates, 'scopes'=>$scopes, 'scope'=>$scope,
            'results'=>$results, 'candidates'=>$candidates,
            'active_template_id'=>$templates->firstWhere('is_active', true)['id'] ?? null,
            'default_body'=>self::DEFAULT_BODY,
            'placeholders'=>['{{nama_tim}}','{{nama_peserta}}','{{jenis_penerima}}','{{peran_penerima}}','{{nisn}}','{{sekolah}}','{{kota_sekolah}}','{{nama_lomba}}','{{kota_pelaksanaan}}','{{peringkat}}','{{nama_beasiswa}}','{{besaran_beasiswa}}','{{nomor_loa}}','{{tanggal_terbit}}','{{tahun}}','{{daftar_pemain}}'],
            'default_awards'=>self::DEFAULT_AWARDS,
        ];
    }

    public function storeTemplate(Request $request)
    {
        return $this->saveTemplate($request, new ScholarshipLoaTemplate());
    }

    public function updateTemplate(Request $request, ScholarshipLoaTemplate $template)
    {
        abort_unless($template->event_edition_id === $this->edition()->id, 404);
        return $this->saveTemplate($request, $template);
    }

    private function saveTemplate(Request $request, ScholarshipLoaTemplate $template)
    {
        $data = $request->validate([
            'name'=>'required|string|max:160', 'scholarship_name'=>'required|string|max:200',
            'body_template'=>'required|string|max:20000', 'number_pattern'=>'required|string|max:200',
            'signing_city'=>'nullable|string|max:120', 'signatory_name'=>'nullable|string|max:160',
            'signatory_position'=>'nullable|string|max:160', 'is_active'=>'required|boolean',
            'award_rank_1'=>'nullable|string|max:120', 'award_rank_2'=>'nullable|string|max:120',
            'award_rank_3'=>'nullable|string|max:120', 'award_rank_4'=>'nullable|string|max:120',
            'background_file'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'signature_file'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'reference_file'=>'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp|max:15360',
            'remove_background'=>'nullable|boolean', 'remove_signature'=>'nullable|boolean', 'remove_reference'=>'nullable|boolean',
        ]);
        $isNew = ! $template->exists;
        $oldPaths = collect([$template->background_path, $template->signature_path, $template->reference_path])->filter();
        $attributes = collect($data)->except(['background_file','signature_file','reference_file','remove_background','remove_signature','remove_reference','award_rank_1','award_rank_2','award_rank_3','award_rank_4'])->all();
        $currentAwards = $template->award_values ?: self::DEFAULT_AWARDS;
        $attributes['award_values'] = collect(range(1, 4))->mapWithKeys(fn ($rank) => [
            $rank=>trim((string)($data['award_rank_'.$rank] ?? $currentAwards[$rank] ?? self::DEFAULT_AWARDS[$rank])),
        ])->all();
        $attributes['event_edition_id'] = $this->edition()->id;
        $attributes['created_by'] ??= $request->user()->id;

        foreach (['background','signature','reference'] as $type) {
            $column = $type.'_path';
            if ($request->boolean('remove_'.$type)) {
                $attributes[$column] = null;
                if ($type === 'reference') $attributes['reference_name'] = null;
            }
            if ($request->hasFile($type.'_file')) {
                $file = $request->file($type.'_file');
                $attributes[$column] = $file->store('loa-templates/'.$this->edition()->id, 'public');
                if ($type === 'reference') $attributes['reference_name'] = $file->getClientOriginalName();
            }
        }

        DB::transaction(function () use ($template, $attributes) {
            if ($attributes['is_active']) ScholarshipLoaTemplate::where('event_edition_id', $this->edition()->id)->update(['is_active'=>false]);
            $template->fill($attributes)->save();
        });
        if ($isNew && ! ScholarshipLoaTemplate::where('event_edition_id', $this->edition()->id)->where('is_active', true)->exists()) {
            $template->update(['is_active'=>true]);
        }
        $retained = collect([$template->background_path, $template->signature_path, $template->reference_path])->filter();
        $oldPaths->diff($retained)->each(fn ($path) => Storage::disk('public')->delete($path));
        return response()->json($this->templatePayload($template->fresh()->loadCount('issuances')), $isNew ? 201 : 200);
    }

    public function destroyTemplate(ScholarshipLoaTemplate $template)
    {
        abort_unless($template->event_edition_id === $this->edition()->id, 404);
        abort_if($template->issuances()->exists(), 422, 'Template sudah digunakan untuk menerbitkan LOA dan tidak dapat dihapus.');
        collect([$template->background_path,$template->signature_path,$template->reference_path])->filter()->each(fn ($path) => Storage::disk('public')->delete($path));
        $template->delete();
        return response()->noContent();
    }

    public function setWinner(Request $request)
    {
        $data = $request->validate([
            'competition_id'=>'required|integer', 'competition_session_id'=>'nullable|integer',
            'rank'=>'required|integer|min:1|max:4', 'registration_id'=>'required|integer',
        ]);
        [$competition, $session] = $this->competitionAndSession($request);
        $registration = Registration::where('competition_id', $competition->id)->where('competition_session_id', $session?->id)
            ->where('status', 'approved')->findOrFail($data['registration_id']);
        $title = $data['rank'] === 4 ? 'Juara Harapan' : 'Juara '.$data['rank'];
        $result = CompetitionResult::where('competition_id', $competition->id)->where('competition_session_id', $session?->id)
            ->where('rank', $data['rank'])->where('source', 'manual')->first() ?? new CompetitionResult();
        $result->fill([
            'competition_id'=>$competition->id, 'competition_session_id'=>$session?->id,
            'registration_id'=>$registration->id, 'rank'=>$data['rank'], 'title'=>$title,
            'source'=>'manual', 'announced_at'=>now(), 'notes'=>'Ditetapkan melalui pengelolaan LOA beasiswa.',
        ])->save();
        return $result->load('registration:id,full_name,team_name,school_name,school_city');
    }

    private function recipients(Registration $registration): Collection
    {
        $players = $registration->members->values()->map(fn ($member, $index) => [
            'recipient_key'=>'member:'.$member->id,
            'recipient_id'=>$member->id,
            'recipient_name'=>$member->full_name,
            'recipient_type'=>'Pemain',
            'recipient_role'=>'Pemain '.($member->member_order ?: $index + 1),
            'recipient_identifier'=>$member->nisn,
            'recipient_grade'=>$member->grade,
        ]);
        $officials = $registration->officials->values()->map(fn ($official, $index) => [
            'recipient_key'=>'official:'.$official->id,
            'recipient_id'=>$official->id,
            'recipient_name'=>$official->full_name,
            'recipient_type'=>'Official',
            'recipient_role'=>$official->position ?: 'Official '.($official->official_order ?: $index + 1),
            'recipient_identifier'=>null,
            'recipient_grade'=>null,
        ]);
        $recipients = $players->concat($officials)->values();

        if ($recipients->isEmpty()) {
            $recipients->push([
                'recipient_key'=>'registration:'.$registration->id,
                'recipient_id'=>$registration->id,
                'recipient_name'=>$registration->full_name,
                'recipient_type'=>'Peserta',
                'recipient_role'=>'Peserta',
                'recipient_identifier'=>$registration->nisn,
                'recipient_grade'=>$registration->grade,
            ]);
        }

        return $recipients->values()->map(fn ($recipient, $index) => [
            ...$recipient,
            'recipient_order'=>$index + 1,
        ]);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'competition_id'=>'required|integer', 'competition_session_id'=>'nullable|integer',
            'template_id'=>'required|integer|exists:scholarship_loa_templates,id',
        ]);
        [$competition, $session] = $this->competitionAndSession($request);
        $template = ScholarshipLoaTemplate::where('event_edition_id', $this->edition()->id)->findOrFail($request->integer('template_id'));
        $results = $this->effectiveResults($competition, $session);
        abort_if($results->isEmpty(), 422, 'Belum ada data pemenang untuk dibuatkan LOA.');

        $issuances = DB::transaction(function () use ($results, $template, $competition, $session, $request) {
            return $results->flatMap(function (CompetitionResult $result) use ($template, $competition, $session, $request) {
                $registration = $result->registration;
                $recipients = $this->recipients($registration);
                $recipientKeys = $recipients->pluck('recipient_key');
                ScholarshipLoaIssuance::where('competition_result_id', $result->id)
                    ->whereNotIn('recipient_key', $recipientKeys)->delete();

                return $recipients->map(function (array $recipient) use ($result, $registration, $template, $competition, $session, $request) {
                    $sequence = str_pad((string)($result->id * 100 + $recipient['recipient_order']), 6, '0', STR_PAD_LEFT);
                    $snapshot = [
                        'result_id'=>$result->id, 'rank'=>$result->rank, 'rank_title'=>$result->rank === 4 ? 'Juara Harapan' : 'Juara '.$result->rank,
                        'team_name'=>$registration->team_name ?: $registration->full_name, 'participant_name'=>$recipient['recipient_name'],
                        'recipient_name'=>$recipient['recipient_name'], 'recipient_type'=>$recipient['recipient_type'],
                        'recipient_role'=>$recipient['recipient_role'], 'recipient_identifier'=>$recipient['recipient_identifier'],
                        'recipient_grade'=>$recipient['recipient_grade'], 'recipient_order'=>$recipient['recipient_order'],
                        'school_name'=>$registration->school_name, 'school_city'=>$registration->school_city,
                        'competition_title'=>$competition->title, 'city'=>$session?->city ?: $competition->location,
                        'venue'=>$session?->venue ?: $competition->location, 'edition_year'=>$this->edition()->year,
                        'scholarship_award'=>$template->award_values[$result->rank] ?? self::DEFAULT_AWARDS[$result->rank],
                        'sequence'=>$sequence,
                        'members'=>$registration->members->map(fn ($member) => $member->only(['member_order','full_name','nisn','grade']))->values()->all(),
                    ];
                    $number = $this->replace($template->number_pattern, [...$snapshot, 'issued_date'=>now()->translatedFormat('d F Y'), 'scholarship_name'=>$template->scholarship_name]);
                    return ScholarshipLoaIssuance::updateOrCreate([
                        'competition_result_id'=>$result->id,
                        'recipient_key'=>$recipient['recipient_key'],
                    ], [
                        'scholarship_loa_template_id'=>$template->id, 'document_number'=>$number,
                        'snapshot'=>$snapshot, 'issued_at'=>now(), 'issued_by'=>$request->user()->id,
                    ]);
                });
            });
        });
        return response()->json(['message'=>$issuances->count().' LOA individual berhasil disiapkan untuk pemain dan official.', 'issuances'=>$issuances->values()], 201);
    }

    private function replacementValues(array $snapshot, ScholarshipLoaTemplate $template, ScholarshipLoaIssuance $issuance): array
    {
        return [...$snapshot, 'sequence'=>$snapshot['sequence'] ?? str_pad((string)$snapshot['result_id'], 4, '0', STR_PAD_LEFT),
            'document_number'=>$issuance->document_number, 'issued_date'=>$issuance->issued_at->translatedFormat('d F Y'),
            'scholarship_name'=>$template->scholarship_name];
    }

    private function replace(string $text, array $values): string
    {
        $players = collect($values['members'] ?? [])->pluck('full_name')->filter()->implode(', ');
        $map = [
            '{{nama_tim}}'=>$values['team_name'] ?? '-', '{{nama_peserta}}'=>$values['participant_name'] ?? '-',
            '{{jenis_penerima}}'=>$values['recipient_type'] ?? 'Peserta', '{{peran_penerima}}'=>$values['recipient_role'] ?? 'Peserta',
            '{{nisn}}'=>$values['recipient_identifier'] ?? '-',
            '{{sekolah}}'=>$values['school_name'] ?? '-', '{{kota_sekolah}}'=>$values['school_city'] ?? '-',
            '{{nama_lomba}}'=>$values['competition_title'] ?? '-', '{{kota_pelaksanaan}}'=>$values['city'] ?? '-',
            '{{peringkat}}'=>$values['rank_title'] ?? '-', '{{nama_beasiswa}}'=>$values['scholarship_name'] ?? '-',
            '{{besaran_beasiswa}}'=>$values['scholarship_award'] ?? self::DEFAULT_AWARDS[(int)($values['rank'] ?? 4)] ?? '-',
            '{{nomor_loa}}'=>$values['document_number'] ?? '-', '{{tanggal_terbit}}'=>$values['issued_date'] ?? '-',
            '{{tahun}}'=>(string)($values['edition_year'] ?? now()->year), '{{sequence}}'=>$values['sequence'] ?? '-',
            '{{daftar_pemain}}'=>$players ?: ($values['participant_name'] ?? '-'),
        ];
        return strtr($text, $map);
    }

    public function showIssuance(ScholarshipLoaIssuance $issuance)
    {
        $issuance->load(['template','competitionResult.registration.members']);
        abort_unless($issuance->template->event_edition_id === $this->edition()->id, 404);
        $values = $this->replacementValues($issuance->snapshot, $issuance->template, $issuance);
        return [...$issuance->toArray(),
            'rendered_body'=>$this->replace($issuance->template->body_template, $values),
            'template'=>$this->templatePayload($issuance->template),
        ];
    }
}
