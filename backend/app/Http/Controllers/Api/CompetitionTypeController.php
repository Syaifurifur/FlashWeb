<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompetitionType;
use App\Models\EventEdition;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompetitionTypeController extends Controller
{
    public function index(Request $request)
    {
        $editionId = EventEdition::resolveCurrent()->id;
        $query = CompetitionType::query()->withCount([
            'competitions'=>fn ($builder) => $builder->where('event_edition_id', $editionId),
            'registrations'=>fn ($builder) => $builder->where('registrations.event_edition_id', $editionId),
        ])
            ->orderBy('sort_order')->orderBy('name');

        if ($request->boolean('active')) {
            $query->where('is_active', true);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('category_group', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        return response()->json(CompetitionType::create($data)->loadCount('competitions'), 201);
    }

    public function update(Request $request, CompetitionType $competitionType)
    {
        $data = $this->validatedData($request, $competitionType);
        $groupChanged = $data['category_group'] !== $competitionType->category_group;
        if ($data['name'] !== $competitionType->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $competitionType->id);
        }
        $competitionType->update($data);
        if ($groupChanged) {
            $competitionType->competitions()->update(['category' => $data['category_group']]);
        }

        return $competitionType->fresh()->loadCount('competitions');
    }

    public function destroy(CompetitionType $competitionType)
    {
        if ($competitionType->competitions()->exists()) {
            return response()->json([
                'message' => 'Jenis lomba masih digunakan. Pindahkan lomba ke jenis lain sebelum menghapusnya.',
            ], 422);
        }

        $competitionType->delete();

        return response()->noContent();
    }

    private function validatedData(Request $request, ?CompetitionType $competitionType = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('competition_types', 'name')->ignore($competitionType?->id)],
            'category_group' => ['required', Rule::in(['Sport Competition', 'Talent Competition', 'Science Competition'])],
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'required|integer|min:0|max:999',
            'is_active' => 'required|boolean',
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'jenis-lomba';
        $slug = $base;
        $counter = 2;
        while (CompetitionType::where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
