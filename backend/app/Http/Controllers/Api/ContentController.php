<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\SiteContent;
use App\Models\EventEdition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContentController extends Controller
{
    private const KEY = 'home_hero';
    private const EXTRAS_KEY = 'landing_extras';
    private const CONSENT_KEY = 'data_consent';
    private const GENERAL_DOCUMENTS_KEY = 'general_documents';

    private const DEFAULT_CONTENT = [
        'badge' => 'Musim kompetisi 2026',
        'title_primary' => 'YOUR TALENT.',
        'title_accent' => 'YOUR ARENA.',
        'description' => 'BSI Flash 2027 merupakan rangkaian kompetisi pelajar tingkat nasional yang diselenggarakan secara hybrid, baik daring maupun luring, untuk siswa SLTA dan sederajat dari berbagai daerah di Indonesia. Program ini menjadi ruang bagi generasi muda untuk mengembangkan kreativitas, sportivitas, kemampuan akademik, keterampilan digital, kepemimpinan, kerja sama tim, serta keberanian dalam menunjukkan potensi terbaiknya. Melalui kategori Sport Competition, Talent Competition, dan Science Competition, setiap peserta memperoleh pengalaman berkompetisi yang terarah, bertemu dengan pelajar berbakat lainnya, mendapatkan informasi kegiatan yang transparan, serta memantau seluruh proses pendaftaran melalui satu dashboard. BSI Flash tidak hanya menghadirkan perlombaan, tetapi juga membangun ekosistem pembelajaran yang mendorong lahirnya generasi mandiri, berprestasi, adaptif, dan siap memberikan kontribusi positif untuk masa depan Indonesia yang lebih baik.',
        'primary_button_label' => 'Temukan Lomba',
        'primary_button_url' => '/lomba',
        'secondary_button_label' => 'Masuk Dashboard',
        'secondary_button_url' => '/login',
        'hashtag' => '#BERANIUNGGUL',
    ];

    private const DEFAULT_EXTRAS = [
        'testimonial_title' => 'Cerita dari peserta BSI Flash',
        'testimonial_interval' => 6,
        'testimonials' => [],
        'sponsor_title' => 'Didukung oleh',
        'sponsors' => [],
        'media_partner_title' => 'Media Partners',
        'media_partners' => [],
    ];

    private const DEFAULT_CONSENT = [
        'title' => 'Persetujuan penggunaan data',
        'checkbox_label' => 'Saya telah membaca rincian di atas dan menyetujui penggunaan data untuk pendaftaran dan verifikasi lomba.',
        'security_note' => 'Password akun tidak pernah ditampilkan kepada panitia. Data sensitif hanya digunakan oleh petugas yang berwenang untuk pemeriksaan pendaftaran.',
        'items' => [
            ['title'=>'Identitas peserta','description'=>'Nama, NISN, tempat/tanggal lahir, kelas, dan nama ibu kandung untuk verifikasi.'],
            ['title'=>'Kontak dan sekolah','description'=>'Email, WhatsApp, asal/alamat sekolah, serta data guru pendamping.'],
            ['title'=>'Data tim','description'=>'Biodata anggota dan official sesuai format lomba.'],
            ['title'=>'Dokumen','description'=>'Kartu pelajar, pas foto, logo sekolah, surat pernyataan, Surat Rekomendasi Sekolah, dan bukti pembayaran bila diwajibkan.'],
            ['title'=>'Proses verifikasi','description'=>'Status kelengkapan, validasi NISN, pembayaran, catatan panitia, dan keputusan pendaftaran.'],
        ],
    ];

    private function stored(string $key, bool $public = false): array
    {
        return SiteContent::where('event_edition_id', EventEdition::resolveCurrent($public)->id)
            ->where('key', $key)->value('content') ?? [];
    }

    private function save(string $key, array $content, Request $request): array
    {
        return SiteContent::updateOrCreate([
            'event_edition_id'=>EventEdition::resolveCurrent()->id,
            'key'=>$key,
        ], [
            'content'=>$content,
            'updated_by'=>$request->user()->id,
        ])->content;
    }

    public function hero()
    {
        $stored = $this->stored(self::KEY, true);
        return array_replace(self::DEFAULT_CONTENT, array_intersect_key($stored, self::DEFAULT_CONTENT));
    }

    public function manageHero()
    {
        return array_replace(self::DEFAULT_CONTENT, array_intersect_key($this->stored(self::KEY), self::DEFAULT_CONTENT));
    }

    public function landingExtras()
    {
        $stored = $this->stored(self::EXTRAS_KEY, true);
        return array_replace(self::DEFAULT_EXTRAS, array_intersect_key($stored, self::DEFAULT_EXTRAS));
    }

    public function manageLandingExtras()
    {
        return array_replace(self::DEFAULT_EXTRAS, array_intersect_key($this->stored(self::EXTRAS_KEY), self::DEFAULT_EXTRAS));
    }

    public function dataConsent()
    {
        return array_replace(self::DEFAULT_CONSENT, $this->stored(self::CONSENT_KEY, true));
    }

    public function manageDataConsent()
    {
        return array_replace(self::DEFAULT_CONSENT, $this->stored(self::CONSENT_KEY));
    }

    public function updateDataConsent(Request $request)
    {
        $data = $request->validate([
            'title'=>'required|string|max:150',
            'checkbox_label'=>'required|string|max:1000',
            'security_note'=>'required|string|max:1000',
            'items'=>'required|array|min:1|max:20',
            'items.*.title'=>'required|string|max:150',
            'items.*.description'=>'required|string|max:2000',
        ]);

        return $this->save(self::CONSENT_KEY, $data, $request);
    }

    public function generalDocuments()
    {
        return $this->stored(self::GENERAL_DOCUMENTS_KEY, true) ?: ['documents'=>[]];
    }

    public function manageGeneralDocuments()
    {
        return $this->stored(self::GENERAL_DOCUMENTS_KEY) ?: ['documents'=>[]];
    }

    public function downloadGeneralDocument(int $document)
    {
        $documents = $this->generalDocuments()['documents'] ?? [];

        return $this->downloadDocument($documents, $document);
    }

    public function downloadCompetitionDocument(Competition $competition, int $document)
    {
        abort_unless(
            (int) $competition->event_edition_id === (int) EventEdition::resolveCurrent(true)->id,
            404,
            'Dokumen tidak ditemukan.'
        );

        return $this->downloadDocument($competition->downloadable_documents ?? [], $document);
    }

    public function updateGeneralDocuments(Request $request)
    {
        $data = $request->validate([
            'documents'=>'nullable|array|max:20',
            'documents.*.title'=>'required|string|max:150',
            'documents.*.description'=>'nullable|string|max:500',
            'documents.*.file_path'=>'nullable|string|max:1000',
            'documents.*.original_name'=>'nullable|string|max:255',
            'document_files'=>'nullable|array|max:20',
            'document_files.*'=>'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip|max:10240',
        ]);
        $current = $this->manageGeneralDocuments()['documents'] ?? [];
        $currentPaths = collect($current)->pluck('file_path')->filter();
        $documents = [];
        foreach ($data['documents'] ?? [] as $index => $document) {
            $path = $document['file_path'] ?? null;
            $name = $document['original_name'] ?? null;
            if ($request->hasFile("document_files.$index")) {
                $file = $request->file("document_files.$index");
                $path = $file->store('site-content/general-documents', 'public');
                $name = $file->getClientOriginalName();
            } elseif (! $path || ! $currentPaths->contains($path)) {
                return response()->json(['message'=>"Pilih file untuk dokumen ke-".($index + 1).'.'], 422);
            }
            $documents[] = [
                'title'=>$document['title'], 'description'=>$document['description'] ?? '',
                'file_path'=>$path, 'original_name'=>$name ?: basename($path),
            ];
        }
        $retained = collect($documents)->pluck('file_path');
        $currentPaths->diff($retained)->each(fn ($path) => Storage::disk('public')->delete($path));

        return $this->save(self::GENERAL_DOCUMENTS_KEY, ['documents'=>$documents], $request);
    }

    private function downloadDocument(array $documents, int $index)
    {
        $document = $documents[$index] ?? null;
        $path = is_array($document) ? ($document['file_path'] ?? null) : null;

        abort_unless(
            is_string($path) && $path !== '' && Storage::disk('public')->exists($path),
            404,
            'Dokumen tidak ditemukan atau file sudah tidak tersedia.'
        );

        $originalName = is_string($document['original_name'] ?? null)
            ? basename(str_replace('\\', '/', $document['original_name']))
            : basename($path);
        $mimeType = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

        return Storage::disk('public')->download($path, $originalName, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function updateLandingExtras(Request $request)
    {
        $data = $request->validate([
            'testimonial_title'=>'sometimes|required|string|max:150',
            'testimonial_interval'=>'sometimes|required|integer|min:3|max:15',
            'testimonials_present'=>'sometimes|boolean',
            'testimonials'=>'sometimes|array|max:30',
            'testimonials.*.name'=>'required|string|max:120',
            'testimonials.*.role'=>'required|string|max:180',
            'testimonials.*.testimonial'=>'required|string|max:1500',
            'testimonials.*.photo_url'=>'nullable|string|max:1000',
            'testimonials.*.is_active'=>'required|boolean',
            'testimonial_photos'=>'nullable|array|max:30',
            'testimonial_photos.*'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'sponsor_title'=>'required|string|max:120', 'sponsors'=>'sometimes|array|max:20',
            'sponsors.*.name'=>'required|string|max:120', 'sponsors.*.logo_url'=>'nullable|string|max:1000',
            'sponsors.*.website_url'=>['nullable','string','max:1000','regex:#^https?://#i'],
            'sponsor_logos'=>'nullable|array|max:20', 'sponsor_logos.*'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'media_partner_title'=>'required|string|max:120', 'media_partners'=>'sometimes|array|max:20',
            'media_partners.*.name'=>'required|string|max:120', 'media_partners.*.logo_url'=>'nullable|string|max:1000',
            'media_partners.*.website_url'=>['nullable','string','max:1000','regex:#^https?://#i'],
            'media_partner_logos'=>'nullable|array|max:20', 'media_partner_logos.*'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $current = $this->manageLandingExtras();
        $testimonials = $current['testimonials'] ?? [];
        if ($request->boolean('testimonials_present') || array_key_exists('testimonials', $data)) {
            $testimonials = [];
            foreach ($data['testimonials'] ?? [] as $index => $testimonial) {
                $photoUrl = $testimonial['photo_url'] ?? null;
                if ($request->hasFile("testimonial_photos.$index")) {
                    $photoUrl = '/storage/'.$request->file("testimonial_photos.$index")->store('site-content/testimonials', 'public');
                }
                if ($photoUrl && ! preg_match('#^(/|https?://)#', $photoUrl)) {
                    throw ValidationException::withMessages(["testimonials.$index.photo_url"=>'Masukkan URL foto yang valid atau unggah foto testimoni.']);
                }
                $testimonials[] = [
                    'name'=>$testimonial['name'],
                    'role'=>$testimonial['role'],
                    'testimonial'=>$testimonial['testimonial'],
                    'photo_url'=>$photoUrl,
                    'is_active'=>(bool) $testimonial['is_active'],
                ];
            }
        }

        $sponsors = [];
        foreach ($data['sponsors'] ?? [] as $index => $sponsor) {
            $logoUrl = $sponsor['logo_url'] ?? null;
            if ($request->hasFile("sponsor_logos.$index")) {
                $logoUrl = '/storage/'.$request->file("sponsor_logos.$index")->store('site-content/sponsors', 'public');
            }
            if (!$logoUrl || !preg_match('#^(/|https?://)#', $logoUrl)) {
                throw ValidationException::withMessages(["sponsors.$index.logo_url"=>'Pilih file atau masukkan URL logo sponsor yang valid.']);
            }
            $sponsors[] = array_merge($sponsor, ['logo_url'=>$logoUrl]);
        }

        $mediaPartners = [];
        foreach ($data['media_partners'] ?? [] as $index => $partner) {
            $logoUrl = $partner['logo_url'] ?? null;
            if ($request->hasFile("media_partner_logos.$index")) {
                $logoUrl = '/storage/'.$request->file("media_partner_logos.$index")->store('site-content/media-partners', 'public');
            }
            if (!$logoUrl || !preg_match('#^(/|https?://)#', $logoUrl)) {
                throw ValidationException::withMessages(["media_partners.$index.logo_url"=>'Pilih file atau masukkan URL logo media partner yang valid.']);
            }
            $mediaPartners[] = array_merge($partner, ['logo_url'=>$logoUrl]);
        }

        $content = [
            'testimonial_title'=>$data['testimonial_title'] ?? $current['testimonial_title'],
            'testimonial_interval'=>(int) ($data['testimonial_interval'] ?? $current['testimonial_interval']),
            'testimonials'=>$testimonials,
            'sponsor_title'=>$data['sponsor_title'], 'sponsors'=>$sponsors,
            'media_partner_title'=>$data['media_partner_title'], 'media_partners'=>$mediaPartners,
        ];
        return $this->save(self::EXTRAS_KEY, $content, $request);
    }

    public function updateHero(Request $request)
    {
        $data = $request->validate([
            'badge'=>'required|string|max:80', 'title_primary'=>'required|string|max:100',
            'title_accent'=>'required|string|max:100', 'description'=>'required|string|max:5000',
            'primary_button_label'=>'required|string|max:50',
            'primary_button_url'=>['required','string','max:255','regex:#^(/|https?://)#'],
            'secondary_button_label'=>'required|string|max:50',
            'secondary_button_url'=>['required','string','max:255','regex:#^(/|https?://)#'],
            'hashtag'=>'required|string|max:50',
        ]);
        return $this->save(self::KEY, $data, $request);
    }
}
