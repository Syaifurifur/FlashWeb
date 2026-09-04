<?php

namespace App\Support;

use App\Models\TournamentDraw;
use App\Models\TournamentMatch;

class TournamentBracketResolver
{
    private const RESOLVED_STATUSES = ['completed', 'walkover', 'bye'];

    public function repairDraw(TournamentDraw $draw): void
    {
        $draw->matches()
            ->orderBy('round_number')
            ->orderBy('match_number')
            ->get()
            ->each(fn (TournamentMatch $match) => $this->resolveMatch($match));

        $draw->unsetRelation('matches');
    }

    public function resolveDependents(TournamentMatch $source): void
    {
        TournamentMatch::where('source_a_match_id', $source->id)
            ->orWhere('source_b_match_id', $source->id)
            ->get()
            ->each(fn (TournamentMatch $match) => $this->resolveMatch($match));
    }

    public function resolveMatch(TournamentMatch $match): void
    {
        $match->refresh();

        if (! $match->source_a_match_id && ! $match->source_b_match_id) {
            $this->normalizeAutomaticBye($match);
            return;
        }

        $allSourcesResolved = true;
        foreach (['a', 'b'] as $slot) {
            $sourceId = $match->{'source_'.$slot.'_match_id'};
            if (! $sourceId) continue;

            $source = TournamentMatch::find($sourceId);
            if (! $source || ! in_array($source->status, self::RESOLVED_STATUSES, true)) {
                $allSourcesResolved = false;
                $match->{'participant_'.$slot.'_id'} = null;
                continue;
            }

            $outcome = $match->{'source_'.$slot.'_outcome'};
            $match->{'participant_'.$slot.'_id'} = $outcome === 'winner'
                ? $source->winner_id
                : ($source->winner_id === $source->participant_a_id ? $source->participant_b_id : $source->participant_a_id);
        }

        if ($match->isDirty()) $match->save();

        if ($allSourcesResolved) $this->normalizeAutomaticBye($match);
    }

    private function normalizeAutomaticBye(TournamentMatch $match): void
    {
        $participants = array_values(array_filter([
            $match->participant_a_id,
            $match->participant_b_id,
        ]));

        if (count($participants) !== 1) return;

        $match->forceFill([
            'winner_id' => $participants[0],
            'status' => 'bye',
            'score_a' => null,
            'score_b' => null,
            'set_scores' => null,
            'scheduled_at' => null,
            'venue' => null,
        ]);

        if (! $match->isDirty()) return;

        $match->save();
        $this->resolveDependents($match);
    }
}
