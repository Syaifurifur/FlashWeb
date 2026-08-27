<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'activity_start_date' => 'date',
            'activity_end_date' => 'date',
            'competition_start_date' => 'date',
            'competition_end_date' => 'date',
            'information_at' => 'datetime',
            'team_update_deadline_at' => 'datetime',
            'submission_start_at' => 'datetime',
            'submission_end_at' => 'datetime',
            'timeline' => 'array',
            'schedule_venues' => 'array',
            'judging_locked_at' => 'datetime',
            'results_announced_at' => 'datetime',
            'fee' => 'decimal:2',
            'quota' => 'integer',
            'pic_slots' => 'integer',
            'supervisor_slots' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function competition() { return $this->belongsTo(Competition::class); }
    public function venueRecord() { return $this->belongsTo(CompetitionVenue::class, 'venue_id'); }
    public function pic() { return $this->belongsTo(User::class, 'pic_user_id'); }
    public function supervisor() { return $this->belongsTo(User::class, 'supervisor_user_id'); }
    public function staff() { return $this->belongsToMany(User::class, 'competition_session_staff')->withPivot(['role','sort_order'])->withTimestamps(); }
    public function pics() { return $this->staff()->wherePivot('role', 'pic')->orderByPivot('sort_order'); }
    public function supervisors() { return $this->staff()->wherePivot('role', 'spv')->orderByPivot('sort_order'); }
    public function registrations() { return $this->hasMany(Registration::class); }
    public function tournamentDraws() { return $this->hasMany(TournamentDraw::class); }
    public function scheduleBlocks() { return $this->hasMany(TournamentScheduleBlock::class); }
    public function notifications() { return $this->hasMany(CompetitionNotification::class); }
    public function results() { return $this->hasMany(CompetitionResult::class)->orderBy('rank'); }
}
