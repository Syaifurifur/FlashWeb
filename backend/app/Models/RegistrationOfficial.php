<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationOfficial extends Model
{
    protected $guarded = [];

    public function registration() { return $this->belongsTo(Registration::class); }
}
