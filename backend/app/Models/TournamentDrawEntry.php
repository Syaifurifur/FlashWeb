<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TournamentDrawEntry extends Model { protected $guarded=[]; protected function casts(): array{return ['is_bye'=>'boolean'];} public function registration(){return $this->belongsTo(Registration::class);} }
