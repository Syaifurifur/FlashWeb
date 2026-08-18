<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompetitionVenue;
use App\Models\EventEdition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VenueController extends Controller
{
    public function index(Request $request)
    {
        $query = CompetitionVenue::query()->where('event_edition_id', EventEdition::resolveCurrent()->id)->with(['pic:id,name,whatsapp','supervisor:id,name,whatsapp'])->withCount('sessions')->orderBy('city')->orderBy('name');

        if ($request->boolean('with_assignments')) {
            $query->with(['sessions'=>fn ($sessions) => $sessions
                ->with(['competition:id,title,category','pics:id,name,whatsapp','supervisors:id,name,whatsapp'])
                ->withCount('registrations')
                ->orderBy('sort_order')
                ->orderBy('id')]);
        }

        if ($request->boolean('active')) {
            $query->where('is_active', true);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['slug'] = $this->uniqueSlug($data['city']);
        $venue = CompetitionVenue::create($data);

        return response()->json($venue->load(['pic:id,name,whatsapp','supervisor:id,name,whatsapp'])->loadCount('sessions'), 201);
    }

    public function update(Request $request, CompetitionVenue $venue)
    {
        abort_unless($venue->event_edition_id === EventEdition::resolveCurrent()->id, 404);
        $venue->update($this->validatedData($request));

        return $venue->fresh()->load(['pic:id,name,whatsapp','supervisor:id,name,whatsapp'])->loadCount('sessions');
    }

    public function updateStaffAssignments(Request $request, CompetitionVenue $venue)
    {
        abort_unless($venue->event_edition_id === EventEdition::resolveCurrent()->id, 404);
        $data = $request->validate([
            'assignments'=>'required|array|min:1|max:100',
            'assignments.*.session_id'=>'required|integer|distinct|exists:competition_sessions,id',
            'assignments.*.pic_slots'=>'required|integer|min:1|max:10',
            'assignments.*.supervisor_slots'=>'required|integer|min:1|max:10',
            'assignments.*.pic_ids'=>'required|array|min:1|max:10',
            'assignments.*.pic_ids.*'=>'required|integer|distinct|exists:users,id',
            'assignments.*.supervisor_ids'=>'required|array|min:1|max:10',
            'assignments.*.supervisor_ids.*'=>'required|integer|distinct|exists:users,id',
        ]);

        return DB::transaction(function () use ($data, $venue) {
            $sessions = $venue->sessions()->whereIn('id', collect($data['assignments'])->pluck('session_id'))->get()->keyBy('id');
            foreach ($data['assignments'] as $assignment) {
                $session = $sessions->get((int) $assignment['session_id']);
                abort_unless($session, 422, 'Sesi lomba tidak terdaftar pada tempat pelaksanaan ini.');
                $picIds = collect($assignment['pic_ids'])->map(fn ($id) => (int) $id)->unique()->values();
                $supervisorIds = collect($assignment['supervisor_ids'])->map(fn ($id) => (int) $id)->unique()->values();
                abort_unless($picIds->count() === (int) $assignment['pic_slots'], 422, 'Jumlah PIC terpilih harus sama dengan jumlah slot PIC.');
                abort_unless($supervisorIds->count() === (int) $assignment['supervisor_slots'], 422, 'Jumlah SPV terpilih harus sama dengan jumlah slot SPV.');
                abort_unless(User::whereIn('id', $picIds)->where('role', 'pic')->count() === $picIds->count(), 422, 'Seluruh petugas PIC harus menggunakan akun PIC.');
                abort_unless(User::whereIn('id', $supervisorIds)->where('role', 'spv')->count() === $supervisorIds->count(), 422, 'Seluruh petugas SPV harus menggunakan akun SPV.');

                $session->update([
                    'pic_user_id'=>$picIds->first(),
                    'supervisor_user_id'=>$supervisorIds->first(),
                    'pic_slots'=>(int) $assignment['pic_slots'],
                    'supervisor_slots'=>(int) $assignment['supervisor_slots'],
                ]);
                $staff = [];
                foreach ($picIds as $index => $userId) $staff[$userId] = ['role'=>'pic','sort_order'=>$index];
                foreach ($supervisorIds as $index => $userId) $staff[$userId] = ['role'=>'spv','sort_order'=>$index];
                $session->staff()->sync($staff);
            }

            return $venue->fresh()->load([
                'pic:id,name,whatsapp',
                'supervisor:id,name,whatsapp',
                'sessions'=>fn ($sessions) => $sessions
                    ->with(['competition:id,title,category','pics:id,name,whatsapp','supervisors:id,name,whatsapp'])
                    ->withCount('registrations')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])->loadCount('sessions');
        });
    }

    public function destroy(CompetitionVenue $venue)
    {
        abort_unless($venue->event_edition_id === EventEdition::resolveCurrent()->id, 404);
        if ($venue->sessions()->exists()) {
            return response()->json([
                'message' => 'Tempat masih digunakan pada sesi lomba. Nonaktifkan tempat atau pindahkan sesi terlebih dahulu.',
            ], 422);
        }

        $venue->delete();

        return response()->noContent();
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:180',
            'city' => 'required|string|max:120',
            'address' => 'required|string|max:1000',
            'activity_start_date' => 'required|date',
            'activity_end_date' => 'required|date|after_or_equal:activity_start_date',
            'field_photo_url' => 'required|url|max:1000',
            'pic_user_id' => 'required|integer|exists:users,id',
            'supervisor_user_id' => 'required|integer|exists:users,id',
            'maps_url' => 'nullable|url|max:1000',
            'contact_name' => 'nullable|string|max:120',
            'contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'required|boolean',
        ]);

        $pic = User::findOrFail($data['pic_user_id']);
        if ($pic->role !== 'pic') {
            abort(422, 'Penanggung jawab kota harus menggunakan akun PIC.');
        }
        $supervisor = User::findOrFail($data['supervisor_user_id']);
        if ($supervisor->role !== 'spv') {
            abort(422, 'Supervisor kota harus menggunakan akun SPV.');
        }

        return $data;
    }

    private function uniqueSlug(string $city): string
    {
        $base = Str::slug($city) ?: 'kota';
        if (CompetitionVenue::where('slug', $base)->exists()) $base .= '-'.EventEdition::resolveCurrent()->year;
        $slug = $base;
        $counter = 2;
        while (CompetitionVenue::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }
        return $slug;
    }
}
