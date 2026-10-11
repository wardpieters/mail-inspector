<?php

namespace Tests\Integration;

use App\Mail\Scans\ScanRepository;
use App\Models\ScanEndpoint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresConcurrencyTest extends TestCase
{
    public function test_concurrent_workers_cannot_both_claim_the_same_endpoint(): void
    {
        if (! env('MAIL_TEST_INTEGRATION') || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires isolated Postgres and process control.');
        }
        config(['database.default' => 'pgsql', 'database.connections.pgsql.host' => 'postgres-test', 'database.connections.pgsql.database' => 'fixture', 'database.connections.pgsql.username' => 'fixture', 'database.connections.pgsql.password' => 'fixture-only']);
        DB::purge('pgsql');
        Artisan::call('migrate:fresh', ['--force' => true]);
        $endpoint = ScanEndpoint::factory()->create();
        $id = $endpoint->id;
        DB::disconnect('pgsql');
        $children = [];
        $channels = [];
        for ($i = 0; $i < 2; $i++) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $pid = pcntl_fork();
            if ($pid === 0) {
                fclose($pair[0]);
                fread($pair[1], 1);
                try {
                    $token = app(ScanRepository::class)->claimEndpoint(ScanEndpoint::findOrFail($id));
                    fwrite($pair[1], json_encode(['token' => $token])."\n");
                    exit(0);
                } catch (\Throwable $e) {
                    fwrite($pair[1], json_encode(['error' => $e->getMessage()])."\n");
                    exit(1);
                }
            }
            $this->assertGreaterThan(0, $pid);
            fclose($pair[1]);
            stream_set_timeout($pair[0], 10);
            $channels[] = $pair[0];
            $children[] = $pid;
        }
        foreach ($channels as $channel) {
            fwrite($channel, 'G');
        }
        $tokens = [];
        foreach ($channels as $channel) {
            $result = json_decode(fgets($channel), true, 512, JSON_THROW_ON_ERROR);
            $this->assertArrayNotHasKey('error', $result);
            $tokens[] = $result['token'];
            fclose($channel);
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        $this->assertCount(1, array_filter($tokens));
        $endpoint->refresh();
        $this->assertSame(1, $endpoint->attempts);
        $this->assertSame(array_values(array_filter($tokens))[0], $endpoint->lease_token);
    }
}
