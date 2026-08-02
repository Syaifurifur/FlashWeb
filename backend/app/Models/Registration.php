<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Registration $registration) {
            $registration->event_edition_id ??= Competition::find($registration->competition_id)?->event_edition_id
                ?? EventEdition::resolveCurrent()->id;
        });
    }

    protected $hidden = ['mother_name'];

    protected function casts(): array
    {
        return [
            'mother_name' => 'encrypted',
            'birth_date' => 'date',
            'consent' => 'boolean',
            'payment_verified_at' => 'datetime',
            'team_completed_at' => 'datetime',
            'documents_completed_at' => 'datetime',
            'work_submitted_at' => 'datetime',
            'work_verified_at' => 'datetime',
        ];
    }

    public function competition() { return $this->belongsTo(Competition::class); }
    public function eventEdition() { return $this->belongsTo(EventEdition::class); }
    public function competitionSession() { return $this->belongsTo(CompetitionSession::class); }
    public function members() { return $this->hasMany(RegistrationMember::class)->orderBy('member_order'); }
    public function officials() { return $this->hasMany(RegistrationOfficial::class)->orderBy('official_order'); }
    public function user() { return $this->belongsTo(User::class); }
    public function judgeAssignments() { return $this->hasMany(JudgeAssignment::class); }
    public function competitionResults() { return $this->hasMany(CompetitionResult::class); }
}
