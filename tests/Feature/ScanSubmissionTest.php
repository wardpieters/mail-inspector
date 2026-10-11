<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ScanSubmissionTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void {parent::setUp();\Illuminate\Support\Facades\Queue::fake();}

 public function test_submits_normalized_domain_with_outbox(): void {
  $this->post('/scans',['domain'=>'EXAMPLE.COM.','selectors'=>[]])->assertRedirect();
  $this->assertDatabaseHas('scans',['domain'=>'example.com','status'=>'queued']);
  $this->assertDatabaseCount('scan_outbox',1);
 }
 public function test_same_idna_domain_reuses_active_report_without_changing_selectors(): void {
  $a=$this->post('/scans',['domain'=>'bücher.example','selectors'=>['first']]);
  $b=$this->post('/scans',['domain'=>'xn--bcher-kva.example','selectors'=>['second']]);
  $this->assertSame($a->headers->get('Location'),$b->headers->get('Location'));
  $this->assertDatabaseCount('scans',1);
  $this->assertSame(['first'],json_decode(\DB::table('scans')->value('selectors'),true));
 }
 public function test_rejects_unsafe_inputs_and_excess_selectors(): void {
  foreach(['https://example.com','127.0.0.1',"bad\r\nexample.com",'name@example.com','a..com'] as $i=>$domain)
   $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.'.($i+1)])->post('/scans',['domain'=>$domain])->assertSessionHasErrors('domain');
  $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.99'])->post('/scans',['domain'=>'example.com','selectors'=>array_map(fn($i)=>'s'.$i,range(1,11))])->assertSessionHasErrors('selectors');
 }
 public function test_sixth_submission_is_rate_limited(): void { for($i=0;$i<5;$i++) $this->post('/scans',['domain'=>'example.com'])->assertRedirect(); $this->post('/scans',['domain'=>'example.com'])->assertStatus(429); }
 public function test_unknown_report_returns_404(): void { $this->get('/scans/00000000-0000-4000-8000-000000000000')->assertNotFound(); }
}
