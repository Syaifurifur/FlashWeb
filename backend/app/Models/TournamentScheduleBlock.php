<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentScheduleBlock extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'duration_minutes' => 'integer'];
    }

    public function competition() { return $this->belongsTo(Competition::class); }
    public function tournamentDraw() { return $this->belongsTo(TournamentDraw::class); }
}
