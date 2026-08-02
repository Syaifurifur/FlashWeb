<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventEdition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'activated_at' => 'datetime',
        ];
    }

    public function competitions() { return $this->hasMany(Competition::class); }
    public function venues() { return $this->hasMany(CompetitionVenue::class); }
    public function registrations() { return $this->hasMany(Registration::class); }
    public function siteContents() { return $this->hasMany(SiteContent::class); }

    public static function resolveCurrent(bool $publicOnly = false): self
    {
        $requestedHeader = request()->header('X-BSI-Edition');
        $requested = $requestedHeader ?: request()->query('year');
        $query = static::query();
        if ($publicOnly && ! $requestedHeader) $query->where('status', '!=', 'draft');

        $edition = null;
        if ($requested !== null && $requested !== '') {
            $edition = (clone $query)->where(function ($builder) use ($requested) {
                $builder->where('id', $requested)->orWhere('year', $requested)->orWhere('slug', $requested);
            })->first();
        }

        return $edition
            ?? (clone $query)->where('status', 'active')->first()
            ?? (clone $query)->latest('year')->firstOrFail();
    }
}
