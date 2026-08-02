<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionVenue extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (CompetitionVenue $venue) {
            $venue->event_edition_id ??= EventEdition::resolveCurrent()->id;
        });
    }

    protected function casts(): array
    {
        return [
            'activity_start_date' => 'date',
            'activity_end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function sessions()
    {
        return $this->hasMany(CompetitionSession::class, 'venue_id');
    }

    public function eventEdition() { return $this->belongsTo(EventEdition::class); }

    public function pic()
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }
}
