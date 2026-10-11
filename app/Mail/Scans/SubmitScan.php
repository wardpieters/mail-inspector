<?php
namespace App\Mail\Scans;
use App\Mail\Value\ScanInput;
use App\Models\{Scan,ScanOutbox};
use Illuminate\Support\Facades\{Cache,DB};
use Illuminate\Support\Str;
class SubmitScan {
 public function submit(ScanInput $input): Scan {
  return Cache::lock('submit:'.hash('sha256',$input->domain),10)->block(3,fn()=>DB::transaction(function() use($input){
   $active=Scan::where('domain',$input->domain)->whereIn('status',['queued','running'])->where('deadline','>',now())->first();
   if($active)return $active;
   $scan=Scan::create(['public_id'=>(string)Str::uuid(),'domain'=>$input->domain,'selectors'=>$input->selectors,'status'=>'queued','deadline'=>now()->addSeconds(config('mail_scan.scan_seconds',300))]);
   ScanOutbox::create(['scan_id'=>$scan->id,'job_kind'=>'discover']);return $scan;
  }));
 }
}
