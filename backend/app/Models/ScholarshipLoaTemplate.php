<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipLoaTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active'=>'boolean', 'award_values'=>'array'];
    }

    public function eventEdition() { return $this->belongsTo(EventEdition::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function issuances() { return $this->hasMany(ScholarshipLoaIssuance::class); }
}
