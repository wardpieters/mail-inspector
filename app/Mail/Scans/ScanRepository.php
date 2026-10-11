<?php
namespace App\Mail\Scans;
use App\Models\{Scan,ScanEndpoint,ScanFinding};
use App\Mail\Value\Finding;
class ScanRepository {
 public function putFinding(Scan $scan,?ScanEndpoint $endpoint,Finding $finding): void {
  ScanFinding::updateOrCreate(['scan_id'=>$scan->id,'check_key'=>$finding->key,'scope_key'=>($endpoint?'endpoint:'.$endpoint->id.':':'').$finding->scope],['scan_endpoint_id'=>$endpoint?->id,'outcome'=>$finding->outcome->value,'severity'=>$finding->severity->value,'title'=>$finding->title,'explanation'=>$finding->explanation,'recommendation'=>$finding->recommendation,'evidence'=>$finding->evidence,'duration_ms'=>$finding->durationMs]);
 }
}
