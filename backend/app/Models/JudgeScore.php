<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JudgeScore extends Model { protected $guarded=[]; protected function casts(): array{return ['score'=>'float'];} public function criterion(){return $this->belongsTo(JudgingCriterion::class,'judging_criterion_id');} }
