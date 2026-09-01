<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'whatsapp',
        'password',
        'role',
        'is_active',
        'competition_id',
        'api_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected $appends = ['permissions', 'role_name'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function managedVenues()
    {
        return $this->hasMany(CompetitionVenue::class, 'pic_user_id');
    }

    public function assignedCompetitionSessions()
    {
        return $this->belongsToMany(CompetitionSession::class, 'competition_session_staff')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps();
    }

    public function managesAllLocations(): bool
    {
        return $this->role === 'super_admin' || $this->hasPermission('competitions.manage');
    }

    public function manageableCompetitionSessionsQuery(int $editionId): Builder
    {
        $query = CompetitionSession::query()->whereHas('competition', fn (Builder $competition) =>
            $competition->where('event_edition_id', $editionId)
        );

        if ($this->managesAllLocations()) {
            return $query;
        }

        if ($this->role === 'pic') {
            return $query->where(function (Builder $sessions) {
                $sessions->where('pic_user_id', $this->id)
                    ->orWhereHas('pics', fn (Builder $pics) => $pics->whereKey($this->id));
            });
        }

        if ($this->role === 'spv') {
            return $query->where(function (Builder $sessions) {
                $sessions->where('supervisor_user_id', $this->id)
                    ->orWhereHas('supervisors', fn (Builder $supervisors) => $supervisors->whereKey($this->id));
            });
        }

        return $this->competition_id
            ? $query->where('competition_id', $this->competition_id)
            : $query->whereRaw('1 = 0');
    }

    public function manageableRegistrationsQuery(int $editionId): Builder
    {
        $query = Registration::query()->whereIn(
            'registrations.competition_id',
            $this->manageableCompetitionsQuery($editionId)->select('id')
        );

        if (! $this->managesAllLocations() && in_array($this->role, ['pic', 'spv'], true)) {
            $sessionIds = $this->manageableCompetitionSessionsQuery($editionId)->select('competition_sessions.id');
            $query->where(function (Builder $registrations) use ($sessionIds) {
                $registrations->whereIn('registrations.competition_session_id', $sessionIds);
                if ($this->role === 'pic' && $this->competition_id) {
                    $registrations->orWhere(function (Builder $legacy) {
                        $legacy->whereNull('registrations.competition_session_id')
                            ->where('registrations.competition_id', $this->competition_id)
                            ->whereDoesntHave('competition.sessions');
                    });
                }
            });
        }

        return $query;
    }

    public function manageableCompetitionsQuery(int $editionId): Builder
    {
        $query = Competition::query()->where('event_edition_id', $editionId);

        if ($this->role === 'super_admin' || $this->hasPermission('competitions.manage')) {
            return $query;
        }

        if ($this->role === 'pic') {
            return $query->where(function (Builder $competitions) {
                $competitions->whereHas('sessions', fn (Builder $sessions) => $sessions
                    ->where('pic_user_id', $this->id)
                    ->orWhereHas('pics', fn (Builder $pics) => $pics->whereKey($this->id)));

                if ($this->competition_id) {
                    $competitions->orWhere('competitions.id', $this->competition_id);
                }
            });
        }

        if ($this->role === 'spv') {
            return $query->whereHas('sessions', fn (Builder $sessions) => $sessions
                ->where('supervisor_user_id', $this->id)
                ->orWhereHas('supervisors', fn (Builder $supervisors) => $supervisors->whereKey($this->id)));
        }

        return $query->whereKey($this->competition_id ?? 0);
    }

    public function accessRole() { return $this->belongsTo(AccessRole::class, 'role', 'slug'); }

    public function getPermissionsAttribute(): array
    {
        if ($this->role === 'super_admin') return array_keys(AccessRole::PERMISSIONS);
        if (in_array($this->role, ['pic','spv'], true)) return ['dashboard.view','registrations.view','registrations.review','registrations.export','tickets.view','tickets.review','competitions.view','competitions.edit','competitions.format','notifications.manage','judging.manage','tournaments.manage'];
        if ($this->role === 'judge') return ['dashboard.view','judging.score'];
        return $this->accessRole?->permissions ?? [];
    }

    public function getRoleNameAttribute(): string
    {
        return match($this->role) {'super_admin'=>'Super Admin','pic'=>'PIC Lomba','spv'=>'SPV Kota','judge'=>'Juri','participant'=>'Peserta',default=>$this->accessRole?->name ?? $this->role ?? 'Pengguna'};
    }

    public function hasPermission(string $permission): bool { return in_array($permission,$this->permissions,true); }
}
