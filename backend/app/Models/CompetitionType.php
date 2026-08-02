<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionType extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function competitions()
    {
        return $this->hasMany(Competition::class);
    }

    public function registrations()
    {
        return $this->hasManyThrough(Registration::class, Competition::class);
    }
}
