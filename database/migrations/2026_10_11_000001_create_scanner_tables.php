<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('scans',function(Blueprint $t){$t->id();$t->uuid('public_id')->unique();$t->string('domain')->index();$t->json('selectors');$t->string('status')->index();$t->timestamp('deadline');$t->timestamp('started_at')->nullable();$t->timestamp('finished_at')->nullable();$t->json('capabilities')->nullable();$t->json('routing_snapshot')->nullable();$t->json('policy_snapshot')->nullable();$t->json('summary')->nullable();$t->text('terminal_error')->nullable();$t->string('scanner_version')->default('1.0');$t->timestamps();});
  Schema::create('scan_endpoints',function(Blueprint $t){$t->id();$t->foreignId('scan_id')->constrained()->cascadeOnDelete();$t->string('mx');$t->unsignedInteger('preference');$t->string('ip',45);$t->unsignedTinyInteger('family');$t->boolean('discovery_secure')->default(false);$t->string('status')->default('queued');$t->uuid('lease_token')->nullable();$t->timestamp('lease_expires_at')->nullable();$t->unsignedInteger('attempts')->default(0);$t->json('transport')->nullable();$t->timestamps();$t->unique(['scan_id','mx','ip']);});
  Schema::create('scan_findings',function(Blueprint $t){$t->id();$t->foreignId('scan_id')->constrained()->cascadeOnDelete();$t->foreignId('scan_endpoint_id')->nullable()->constrained('scan_endpoints')->cascadeOnDelete();$t->string('check_key');$t->string('scope_key');$t->string('outcome');$t->string('severity');$t->string('title');$t->text('explanation');$t->text('recommendation')->nullable();$t->json('evidence');$t->unsignedInteger('duration_ms')->default(0);$t->timestamps();$t->unique(['scan_id','check_key','scope_key']);});
  Schema::create('scan_outbox',function(Blueprint $t){$t->id();$t->foreignId('scan_id')->constrained()->cascadeOnDelete();$t->foreignId('scan_endpoint_id')->nullable()->constrained('scan_endpoints')->cascadeOnDelete();$t->string('job_kind');$t->timestamp('dispatched_at')->nullable();$t->timestamps();});
 }
 public function down(): void {foreach(['scan_outbox','scan_findings','scan_endpoints','scans'] as $table) Schema::dropIfExists($table);}
};
