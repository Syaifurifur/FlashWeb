<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JudgingCriterion extends Model { protected $guarded=[]; protected function casts(): array{return ['max_score'=>'float'];} public function competition(){return $this->belongsTo(Competition::class);} }
