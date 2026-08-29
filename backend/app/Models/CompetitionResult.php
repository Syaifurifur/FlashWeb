<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionResult extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'announced_at' => 'datetime', 'rank' => 'integer'];
    }

    public function competition() { return $this->belongsTo(Competition::class); }
    public function competitionSession() { return $this->belongsTo(CompetitionSession::class); }
    public function registration() { return $this->belongsTo(Registration::class); }
    public function scholarshipLoaIssuances() { return $this->hasMany(ScholarshipLoaIssuance::class); }
}
