<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TournamentMatch extends Model {
    protected $guarded=[];
    protected $attributes=['status'=>'unscheduled','duration_minutes'=>60];
    protected function casts(): array{return ['score_a'=>'float','score_b'=>'float','scheduled_at'=>'datetime','duration_minutes'=>'integer'];}
    public function participantA(){return $this->belongsTo(Registration::class,'participant_a_id');}
    public function participantB(){return $this->belongsTo(Registration::class,'participant_b_id');}
    public function winner(){return $this->belongsTo(Registration::class,'winner_id');}
    public function tournamentDraw(){return $this->belongsTo(TournamentDraw::class);}
}
