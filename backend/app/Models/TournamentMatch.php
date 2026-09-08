<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TournamentMatch extends Model {
    protected $guarded=[];
    protected $attributes=['status'=>'unscheduled','duration_minutes'=>60];
    public function hasPlayEvidence(): bool
    {
        if ($this->status === 'bye') return false;
        return in_array($this->status, ['ongoing', 'completed', 'walkover'], true)
            || $this->score_a !== null || $this->score_b !== null || $this->winner_id !== null
            || collect($this->set_scores ?? [])->contains(fn ($set) => ($set['score_a'] ?? null) !== null || ($set['score_b'] ?? null) !== null || ($set['completed'] ?? false));
    }
    protected static function booted(): void
    {
        static::updating(function (TournamentMatch $match) {
            if (!in_array($match->stage, ['knockout', 'third_place'], true)) return;
            $before = (new TournamentMatch)->setRawAttributes($match->getRawOriginal());
            if (!$match->hasPlayEvidence() && !$before->hasPlayEvidence()) return;
            // Keep a permanent start marker even if an operator later resets status/scores.
            // The same draw lock is used by redraw, including updates from the schedule UI.
            \Illuminate\Support\Facades\DB::transaction(function () use ($match) {
                $draw = TournamentDraw::whereKey($match->tournament_draw_id)->lockForUpdate()->firstOrFail();
                abort_unless($draw->matches()->whereKey($match->id)->exists(), 422, 'Bagan sudah diganti. Muat ulang pertandingan terlebih dahulu.');
                if ($draw->format !== 'groups_knockout' || !empty($draw->settings['knockout_started_at'])) return;
                $draw->update(['settings' => array_merge($draw->settings ?? [], ['knockout_started_at' => now()->toIso8601String()])]);
            });
        });
    }
    protected function casts(): array{return ['score_a'=>'float','score_b'=>'float','best_of_sets'=>'integer','set_scores'=>'array','scheduled_at'=>'datetime','duration_minutes'=>'integer'];}
    public function participantA(){return $this->belongsTo(Registration::class,'participant_a_id');}
    public function participantB(){return $this->belongsTo(Registration::class,'participant_b_id');}
    public function winner(){return $this->belongsTo(Registration::class,'winner_id');}
    public function tournamentDraw(){return $this->belongsTo(TournamentDraw::class);}
}
