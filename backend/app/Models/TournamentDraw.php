<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TournamentDraw extends Model {
    protected $guarded=[];
    protected function casts(): array{return ['settings'=>'array','drawn_at'=>'datetime','locked_at'=>'datetime'];}
    public function competition(){return $this->belongsTo(Competition::class);}
    public function operator(){return $this->belongsTo(User::class,'operator_id');}
    public function entries(){return $this->hasMany(TournamentDrawEntry::class)->orderBy('slot_number');}
    public function matches(){return $this->hasMany(TournamentMatch::class)->orderBy('match_number');}
}
