<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionVenue;
use App\Models\EventEdition;
use App\Models\SiteContent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventEditionController extends Controller
{
    public function publicIndex()
    {
        $active = EventEdition::resolveCurrent(true);

        return [
            'active' => $active,
            'editions' => EventEdition::where('status', '!=', 'draft')->orderByDesc('year')->get(),
        ];
    }

    public function index()
    {
        return EventEdition::query()
            ->withCount(['competitions', 'venues', 'registrations'])
            ->orderByDesc('year')
            ->get()
            ->map(function (EventEdition $edition) {
                $competitionIds = $edition->competitions()->pluck('id');
                return [
                    ...$edition->toArray(),
                    'matches_count' => DB::table('tournament_matches')
                        ->join('tournament_draws', 'tournament_draws.id', '=', 'tournament_matches.tournament_draw_id')
                        ->whereIn('tournament_draws.competition_id', $competitionIds)->count(),
                    'scores_count' => DB::table('judge_scores')
                        ->join('judge_assignments', 'judge_assignments.id', '=', 'judge_scores.judge_assignment_id')
                        ->whereIn('judge_assignments.competition_id', $competitionIds)->count(),
                    'winners_count' => DB::table('competition_results')->whereIn('competition_id', $competitionIds)->count(),
                ];
            })->values();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100', Rule::unique('event_editions', 'year')],
            'name' => 'nullable|string|max:160',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'clone_from_id' => 'nullable|integer|exists:event_editions,id',
        ]);

        $edition = DB::transaction(function () use ($data) {
            $edition = EventEdition::create([
                'year' => $data['year'],
                'name' => $data['name'] ?: 'BSI Flash '.$data['year'],
                'slug' => 'bsi-flash-'.$data['year'],
                'status' => 'draft',
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
            ]);

            if (! empty($data['clone_from_id'])) {
                $this->cloneSetup(EventEdition::findOrFail($data['clone_from_id']), $edition);
            }

            return $edition;
        });

        return response()->json($edition->fresh()->loadCount(['competitions', 'venues', 'registrations']), 201);
    }

    public function update(Request $request, EventEdition $edition)
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);
        $edition->update($data);

        return $edition->fresh()->loadCount(['competitions', 'venues', 'registrations']);
    }

    public function activate(EventEdition $edition)
    {
        DB::transaction(function () use ($edition) {
            EventEdition::where('status', 'active')->whereKeyNot($edition->id)->update(['status' => 'archived']);
            $edition->update(['status' => 'active', 'activated_at' => now()]);
        });

        return $edition->fresh()->loadCount(['competitions', 'venues', 'registrations']);
    }

    private function cloneSetup(EventEdition $source, EventEdition $target): void
    {
        $yearDelta = $target->year - $source->year;
        $venueMap = [];

        $source->venues()->orderBy('id')->get()->each(function (CompetitionVenue $venue) use ($target, $yearDelta, &$venueMap) {
            $copy = $venue->replicate();
            $copy->event_edition_id = $target->id;
            $copy->slug = $this->uniqueVenueSlug($venue->slug, $target->year);
            foreach (['activity_start_date', 'activity_end_date'] as $field) {
                $copy->{$field} = $this->shiftDate($venue->{$field}, $yearDelta);
            }
            $copy->save();
            $venueMap[$venue->id] = $copy->id;
        });

        $source->competitions()->with(['sessions.staff', 'judgingCriteria'])->orderBy('id')->get()
            ->each(function (Competition $competition) use ($target, $yearDelta, $venueMap) {
                $copy = $competition->replicate();
                $copy->event_edition_id = $target->id;
                $copy->slug = $this->uniqueCompetitionSlug($competition->slug, $target->year);
                $copy->judging_locked_at = null;
                $copy->results_announced_at = null;
                foreach (['registration_start', 'registration_end', 'event_date', 'team_update_deadline_at', 'document_upload_deadline_at', 'submission_start_at', 'submission_end_at'] as $field) {
                    $copy->{$field} = $this->shiftDate($competition->{$field}, $yearDelta);
                }
                $copy->timeline = $this->shiftTimeline($competition->timeline ?? [], $yearDelta);
                $copy->save();

                foreach ($competition->sessions as $session) {
                    $sessionCopy = $session->replicate();
                    $sessionCopy->competition_id = $copy->id;
                    $sessionCopy->venue_id = $venueMap[$session->venue_id] ?? null;
                    foreach (['activity_start_date', 'activity_end_date', 'competition_start_date', 'competition_end_date', 'information_at', 'team_update_deadline_at', 'submission_start_at', 'submission_end_at'] as $field) {
                        $sessionCopy->{$field} = $this->shiftDate($session->{$field}, $yearDelta);
                    }
                    $sessionCopy->timeline = $this->shiftTimeline($session->timeline ?? [], $yearDelta);
                    $sessionCopy->save();
                    $staff = $session->staff->mapWithKeys(fn ($user) => [
                        $user->id => ['role' => $user->pivot->role, 'sort_order' => $user->pivot->sort_order],
                    ])->all();
                    $sessionCopy->staff()->sync($staff);
                }

                foreach ($competition->judgingCriteria as $criterion) {
                    $criterionCopy = $criterion->replicate();
                    $criterionCopy->competition_id = $copy->id;
                    $criterionCopy->save();
                }
            });

        $source->siteContents()->get()->each(function (SiteContent $content) use ($source, $target) {
            SiteContent::create([
                'event_edition_id' => $target->id,
                'key' => $content->key,
                'content' => $this->replaceYear($content->content, $source->year, $target->year),
                'updated_by' => $content->updated_by,
            ]);
        });
    }

    private function shiftDate($value, int $years)
    {
        return $value ? Carbon::parse($value)->addYears($years) : null;
    }

    private function shiftTimeline(array $timeline, int $years): array
    {
        return collect($timeline)->map(function (array $entry) use ($years) {
            foreach (['date', 'start_date', 'end_date'] as $field) {
                if (! empty($entry[$field]) && ! str_contains((string) $entry[$field], '|')) {
                    $entry[$field] = Carbon::parse($entry[$field])->addYears($years)->toDateString();
                }
            }
            if (! empty($entry['date']) && str_contains((string) $entry['date'], '|')) {
                $entry['date'] = collect(explode('|', $entry['date']))->map(fn ($date) => Carbon::parse($date)->addYears($years)->toDateString())->join('|');
            }
            return $entry;
        })->all();
    }

    private function replaceYear($value, int $sourceYear, int $targetYear)
    {
        if (is_array($value)) return collect($value)->map(fn ($item) => $this->replaceYear($item, $sourceYear, $targetYear))->all();
        return is_string($value) ? str_replace((string) $sourceYear, (string) $targetYear, $value) : $value;
    }

    private function uniqueVenueSlug(string $sourceSlug, int $year): string
    {
        $base = Str::slug(preg_replace('/-20\d{2}$/', '', $sourceSlug).'-'.$year);
        $slug = $base;
        $counter = 2;
        while (CompetitionVenue::where('slug', $slug)->exists()) $slug = $base.'-'.$counter++;
        return $slug;
    }

    private function uniqueCompetitionSlug(string $sourceSlug, int $year): string
    {
        $base = Str::slug(preg_replace('/-20\d{2}$/', '', $sourceSlug).'-'.$year);
        $slug = $base;
        $counter = 2;
        while (Competition::where('slug', $slug)->exists()) $slug = $base.'-'.$counter++;
        return $slug;
    }
}
