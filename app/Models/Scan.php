<?php
namespace App\Models;
use App\Mail\Enums\ScanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Scan extends Model {
 use HasFactory;
 protected $guarded=[];
 protected function casts(): array {return ['selectors'=>'array','status'=>ScanStatus::class,'deadline'=>'immutable_datetime','started_at'=>'immutable_datetime','finished_at'=>'immutable_datetime','capabilities'=>'array','routing_snapshot'=>'array','policy_snapshot'=>'array','summary'=>'array'];}
 public function endpoints(){return $this->hasMany(ScanEndpoint::class);}
 public function findings(){return $this->hasMany(ScanFinding::class);}
 public function terminal(): bool {return in_array($this->status,[ScanStatus::Completed,ScanStatus::Partial,ScanStatus::Failed],true);}
}
