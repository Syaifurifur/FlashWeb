<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventEdition;
use App\Models\Registration;
use App\Models\SupporterTicket;
use App\Services\RegistrationExcelExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupporterTicketController extends Controller
{
    public function settings()
    {
        return $this->settingsPayload(EventEdition::resolveCurrent(true));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'supporter_ticket_price' => 'required|numeric|min:0|max:999999999',
            'supporter_bank_name' => 'nullable|required_with:supporter_bank_account_number,supporter_bank_account_holder|string|max:120',
            'supporter_bank_account_number' => 'nullable|required_with:supporter_bank_name,supporter_bank_account_holder|string|max:80',
            'supporter_bank_account_holder' => 'nullable|required_with:supporter_bank_name,supporter_bank_account_number|string|max:180',
            'supporter_payment_note' => 'nullable|string|max:500',
        ]);

        $edition = EventEdition::resolveCurrent();
        $edition->update($data);

        return response()->json([
            'message' => 'Harga tiket dan informasi transfer berhasil disimpan.',
            ...$this->settingsPayload($edition->fresh()),
        ]);
    }

    public function schools(Request $request)
    {
        $data = $request->validate(['query' => 'nullable|string|max:100']);
        $query = trim($data['query'] ?? '');

        return Registration::query()
            ->where('event_edition_id', EventEdition::resolveCurrent(true)->id)
            ->whereNotNull('school_name')
            ->when($query !== '', fn ($builder) => $builder->where('school_name', 'like', '%'.$query.'%'))
            ->select('school_name')
            ->distinct()
            ->orderBy('school_name')
            ->limit(10)
            ->pluck('school_name')
            ->values();
    }

    public function store(Request $request)
    {
        $edition = EventEdition::resolveCurrent(true);
        $editionId = $edition->id;
        $paymentMethods = $this->transferEnabled($edition) ? ['cash', 'transfer'] : ['cash'];
        $data = $request->validate([
            'competition_venue_id' => ['required', 'integer', Rule::exists('competition_venues', 'id')->where(fn ($query) => $query->where('event_edition_id', $editionId)->where('is_active', true))],
            'full_name' => 'required|string|max:120',
            'grade' => 'required|in:X,XI,XII,other',
            'supporter_category' => 'nullable|required_if:grade,other|in:general,parent',
            'school_name' => 'required|string|max:180',
            'gender' => 'required|in:male,female',
            'email' => 'required|email|max:150',
            'whatsapp' => ['required', 'regex:/^[0-9+]{10,15}$/'],
            'interested_in_college' => 'required|boolean',
            'payment_method' => ['required', Rule::in($paymentMethods)],
            'payment_proof' => [
                Rule::requiredIf(fn () => $request->input('payment_method') === 'transfer'),
                'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120',
            ],
        ], [
            'payment_method.in' => 'Pembayaran transfer belum tersedia karena informasi rekening belum dilengkapi admin.',
        ]);

        return DB::transaction(function () use ($request, $data, $edition, $editionId) {
            unset($data['payment_proof']);
            if ($data['grade'] !== 'other') $data['supporter_category'] = null;
            if ($request->hasFile('payment_proof')) {
                $data['payment_proof_path'] = $request->file('payment_proof')->store('supporter-tickets', 'public');
            }

            $ticket = SupporterTicket::create($data + [
                'event_edition_id' => $editionId,
                'ticket_code' => 'SUPPORTER-'.strtoupper(Str::random(8)),
                'ticket_price' => $edition->supporter_ticket_price,
                'status' => 'pending',
            ]);

            return response()->json([
                'message' => $ticket->payment_method === 'cash'
                    ? 'Pemesanan tiket berhasil. Tunjukkan kode tiket kepada admin untuk verifikasi pembayaran cash.'
                    : 'Pemesanan tiket dan bukti transfer berhasil dikirim. Tunggu verifikasi admin.',
                'ticket_code' => $ticket->ticket_code,
                'status' => $ticket->status,
                'payment_method' => $ticket->payment_method,
                'ticket_price' => $ticket->ticket_price,
                'grade' => $ticket->grade,
                'supporter_category' => $ticket->supporter_category,
                'venue' => $ticket->venue()->first(['id', 'name', 'city']),
            ], 201);
        });
    }

    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:all,pending,verified,rejected',
            'payment_method' => 'nullable|in:all,cash,transfer',
            'venue_id' => 'nullable|integer|exists:competition_venues,id',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|in:10,20,50,100',
        ]);

        $editionId = EventEdition::resolveCurrent()->id;
        $statusCounts = SupporterTicket::query()->where('event_edition_id', $editionId)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $paymentCounts = SupporterTicket::query()->where('event_edition_id', $editionId)
            ->selectRaw('payment_method, COUNT(*) as total')->groupBy('payment_method')->pluck('total', 'payment_method');
        $verifiedRevenue = SupporterTicket::query()
            ->where('event_edition_id', $editionId)
            ->where('status', 'verified')
            ->sum('ticket_price');

        $tickets = SupporterTicket::query()
            ->where('event_edition_id', $editionId)
            ->with(['verifier:id,name', 'venue:id,name,city,address']);

        if ($request->filled('status') && $request->status !== 'all') {
            $tickets->where('status', $request->status);
        }
        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            $tickets->where('payment_method', $request->payment_method);
        }
        if ($request->filled('venue_id')) {
            $tickets->where('competition_venue_id', $request->integer('venue_id'));
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $tickets->where(fn ($query) => $query
                ->where('full_name', 'like', '%'.$search.'%')
                ->orWhere('ticket_code', 'like', '%'.$search.'%')
                ->orWhere('school_name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('whatsapp', 'like', '%'.$search.'%')
                ->orWhereHas('venue', fn ($venue) => $venue->where(fn ($location) => $location
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%'))));
        }

        $result = $tickets->latest()->paginate($request->integer('per_page', 20))->withQueryString()->toArray();
        $result['summary'] = [
            'total' => $statusCounts->sum(),
            'pending' => (int) ($statusCounts['pending'] ?? 0),
            'verified' => (int) ($statusCounts['verified'] ?? 0),
            'sold' => (int) ($statusCounts['verified'] ?? 0),
            'verified_revenue' => (float) $verifiedRevenue,
            'rejected' => (int) ($statusCounts['rejected'] ?? 0),
            'cash' => (int) ($paymentCounts['cash'] ?? 0),
            'transfer' => (int) ($paymentCounts['transfer'] ?? 0),
        ];

        return $result;
    }

    public function export(Request $request, RegistrationExcelExporter $exporter)
    {
        $request->validate([
            'status' => 'nullable|in:all,pending,verified,rejected',
            'payment_method' => 'nullable|in:all,cash,transfer',
            'venue_id' => 'nullable|integer|exists:competition_venues,id',
            'search' => 'nullable|string|max:100',
        ]);

        $tickets = SupporterTicket::query()
            ->where('event_edition_id', EventEdition::resolveCurrent()->id)
            ->with(['verifier:id,name', 'venue:id,name,city,address']);

        if ($request->filled('status') && $request->status !== 'all') {
            $tickets->where('status', $request->status);
        }
        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            $tickets->where('payment_method', $request->payment_method);
        }
        if ($request->filled('venue_id')) {
            $tickets->where('competition_venue_id', $request->integer('venue_id'));
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $tickets->where(fn ($query) => $query
                ->where('full_name', 'like', '%'.$search.'%')
                ->orWhere('ticket_code', 'like', '%'.$search.'%')
                ->orWhere('school_name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('whatsapp', 'like', '%'.$search.'%')
                ->orWhereHas('venue', fn ($venue) => $venue->where(fn ($location) => $location
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%'))));
        }

        $path = $exporter->createSupporterTickets($tickets->latest()->get());

        return response()->download(
            $path,
            'data-tiket-supporter-'.now()->format('Ymd').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend(true);
    }

    public function verify(Request $request, SupporterTicket $supporterTicket)
    {
        $editionId = EventEdition::resolveCurrent()->id;
        abort_unless($supporterTicket->event_edition_id === $editionId, 404);
        $data = $request->validate([
            'competition_venue_id' => ['required', 'integer', Rule::exists('competition_venues', 'id')->where(fn ($query) => $query->where('event_edition_id', $editionId)->where('is_active', true))],
            'status' => 'required|in:pending,verified,rejected',
            'verification_note' => 'nullable|string|max:1000',
        ]);

        if ($data['status'] === 'verified' && $supporterTicket->payment_method === 'transfer' && ! $supporterTicket->payment_proof_path) {
            return response()->json(['message' => 'Bukti transfer belum diunggah. Tiket belum dapat diverifikasi.'], 422);
        }
        if ($data['status'] === 'rejected' && empty($data['verification_note'])) {
            return response()->json(['message' => 'Catatan wajib diisi saat tiket ditolak.'], 422);
        }

        $supporterTicket->update($data + ($data['status'] === 'verified' ? [
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ] : [
            'verified_at' => null,
            'verified_by' => null,
        ]));

        return $supporterTicket->fresh()->load(['verifier:id,name', 'venue:id,name,city,address']);
    }

    private function settingsPayload(EventEdition $edition): array
    {
        return [
            'ticket_price' => (float) $edition->supporter_ticket_price,
            'bank_name' => $edition->supporter_bank_name,
            'bank_account_number' => $edition->supporter_bank_account_number,
            'bank_account_holder' => $edition->supporter_bank_account_holder,
            'payment_note' => $edition->supporter_payment_note,
            'transfer_enabled' => $this->transferEnabled($edition),
        ];
    }

    private function transferEnabled(EventEdition $edition): bool
    {
        return filled($edition->supporter_bank_name)
            && filled($edition->supporter_bank_account_number)
            && filled($edition->supporter_bank_account_holder);
    }
}
