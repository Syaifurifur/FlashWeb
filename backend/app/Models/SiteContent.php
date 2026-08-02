<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteContent extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (SiteContent $content) {
            $content->event_edition_id ??= EventEdition::resolveCurrent()->id;
        });
    }

    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    public function eventEdition() { return $this->belongsTo(EventEdition::class); }
}
