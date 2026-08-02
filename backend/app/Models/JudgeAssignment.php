<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JudgeAssignment extends Model {
    protected $guarded=[];
    protected function casts(): array{return ['submitted_at'=>'datetime'];}
    public function competition(){return $this->belongsTo(Competition::class);}
    public function registration(){return $this->belongsTo(Registration::class);}
    public function judge(){return $this->belongsTo(User::class,'judge_id');}
    public function scores(){return $this->hasMany(JudgeScore::class);}
}
