<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Competition extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Competition $competition) {
            $competition->event_edition_id ??= EventEdition::resolveCurrent()->id;
        });
    }

    protected function casts(): array
    {
        return [
            'registration_start' => 'date',
            'registration_end' => 'date',
            'team_update_deadline_at' => 'datetime',
            'document_upload_deadline_at' => 'datetime',
            'event_date' => 'date',
            'submission_start_at' => 'datetime',
            'submission_end_at' => 'datetime',
            'judging_locked_at' => 'datetime',
            'results_announced_at' => 'datetime',
            'requirements' => 'array',
            'guides' => 'array',
            'downloadable_documents' => 'array',
            'timeline' => 'array',
            'schedule_venues' => 'array',
            'is_featured' => 'boolean',
            'team_size' => 'integer',
            'official_count' => 'integer',
            'pic_slots' => 'integer',
        ];
    }

    public function registrations() { return $this->hasMany(Registration::class); }
    public function eventEdition() { return $this->belongsTo(EventEdition::class); }
    public function competitionType() { return $this->belongsTo(CompetitionType::class); }
    public function sessions() { return $this->hasMany(CompetitionSession::class)->orderBy('sort_order')->orderBy('competition_start_date'); }
    public function pics() { return $this->hasMany(User::class); }
    public function members() { return $this->hasMany(RegistrationMember::class); }
    public function notifications() { return $this->hasMany(CompetitionNotification::class); }
    public function judgingCriteria() { return $this->hasMany(JudgingCriterion::class)->orderBy('sort_order'); }
    public function judgeAssignments() { return $this->hasMany(JudgeAssignment::class); }
    public function tournamentDraws() { return $this->hasMany(TournamentDraw::class); }
    public function scheduleBlocks() { return $this->hasMany(TournamentScheduleBlock::class); }
    public function results() { return $this->hasMany(CompetitionResult::class)->orderBy('rank'); }
}
