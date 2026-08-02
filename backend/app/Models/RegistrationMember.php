<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationMember extends Model
{
    protected $guarded = [];
    protected $hidden = ['mother_name'];

    protected function casts(): array
    {
        return ['mother_name' => 'encrypted', 'birth_date' => 'date', 'nisn_verified_at' => 'datetime'];
    }

    public function registration() { return $this->belongsTo(Registration::class); }
}
