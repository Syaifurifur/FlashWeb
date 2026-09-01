<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupporterTicket extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'interested_in_college' => 'boolean',
            'ticket_price' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function eventEdition() { return $this->belongsTo(EventEdition::class); }
    public function venue() { return $this->belongsTo(CompetitionVenue::class, 'competition_venue_id'); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }
}
