<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionNotification extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (CompetitionNotification $notification) {
            $notification->event_edition_id ??= $notification->competition_id
                ? Competition::find($notification->competition_id)?->event_edition_id
                : EventEdition::resolveCurrent()->id;
        });
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function competition() { return $this->belongsTo(Competition::class); }
    public function competitionSession() { return $this->belongsTo(CompetitionSession::class); }
    public function eventEdition() { return $this->belongsTo(EventEdition::class); }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
}
