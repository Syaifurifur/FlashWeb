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
        if (in_array($this->role, ['pic','spv'], true)) return ['dashboard.view','registrations.view','registrations.review','registrations.export','competitions.view','competitions.edit','competitions.format','notifications.manage','judging.manage','tournaments.manage'];
        if ($this->role === 'judge') return ['dashboard.view','judging.score'];
        return $this->accessRole?->permissions ?? [];
    }

    public function getRoleNameAttribute(): string
    {
        return match($this->role) {'super_admin'=>'Super Admin','pic'=>'PIC Lomba','spv'=>'SPV Kota','judge'=>'Juri','participant'=>'Peserta',default=>$this->accessRole?->name ?? $this->role ?? 'Pengguna'};
    }

    public function hasPermission(string $permission): bool { return in_array($permission,$this->permissions,true); }
}
