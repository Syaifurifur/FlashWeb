<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipLoaIssuance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot'=>'array', 'issued_at'=>'datetime'];
    }

    public function template() { return $this->belongsTo(ScholarshipLoaTemplate::class, 'scholarship_loa_template_id'); }
    public function competitionResult() { return $this->belongsTo(CompetitionResult::class); }
    public function issuer() { return $this->belongsTo(User::class, 'issued_by'); }
}
