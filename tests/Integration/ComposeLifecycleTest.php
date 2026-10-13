<?php

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('compose')]
class ComposeLifecycleTest extends TestCase
{
    private string $project;

    private string $environmentFile;

    private string $overrideFile;

    protected function setUp(): void
    {
        parent::setUp();
        if (! env('MAIL_TEST_COMPOSE')) {
            $this->markTestSkipped('Requires built production images and an isolated Docker project.');
        }
        $this->project = 'mail-accept-'.bin2hex(random_bytes(6));
        $this->environmentFile = tempnam(sys_get_temp_dir(), 'mail-accept-env-');
        unlink($this->environmentFile);
        (new Process(['php', base_path('scripts/setup-env.php'), $this->environmentFile]))->mustRun();
        file_put_contents($this->environmentFile, "\nHTTP_PORT=0\n", FILE_APPEND);
        $this->overrideFile = tempnam(sys_get_temp_dir(), 'mail-accept-override-');
    }

    private function compose(array $arguments, bool $override = false): Process
    {
        $command = ['docker', 'compose', '--env-file', $this->environmentFile, '-p', $this->project, '-f', base_path('compose.yaml')];
        if ($override) {
            $command = [...$command, '-f', $this->overrideFile];
        }
        $process = new Process([...$command, ...$arguments], base_path(), ['APP_KEY' => false, 'DB_PASSWORD' => false, 'REDIS_PASSWORD' => false, 'HTTP_PORT' => false, 'HTTP_BIND' => false], null, 150);
        $process->run();

        return $process;
    }

    protected function tearDown(): void
    {
        if (isset($this->project)) {
            $this->compose(['down', '--volumes', '--remove-orphans']);
            foreach ([$this->environmentFile, $this->overrideFile] as $file) {
                @unlink($file);
            }
        }
        parent::tearDown();
    }

    public function test_failed_migration_prevents_application_workers_and_scheduler_starting(): void
    {
        file_put_contents($this->overrideFile, "services:\n  migrate:\n    entrypoint: [sh, -c]\n    command: ['exit 9']\n");
        $process = $this->compose(['up', '-d', 'app', 'worker', 'scheduler'], true);
        $this->assertFalse($process->isSuccessful());
        $running = $this->compose(['ps', '--status', 'running', '--services'], true)->getOutput();
        foreach (['app', 'worker', 'scheduler'] as $service) {
            $this->assertNotContains($service, explode("\n", trim($running)));
        }
    }

    public function test_reports_and_application_key_survive_restarts_and_backend_address_changes(): void
    {
        $subnet = '10.203.'.random_int(1, 254);
        $configuration = "networks:\n  services:\n    ipam:\n      config:\n        - subnet: {$subnet}.0/24\nservices:\n  app:\n    networks:\n      services:\n        ipv4_address: {$subnet}.20\n      egress: {}\n";
        file_put_contents($this->overrideFile, $configuration);
        $process = $this->compose(['up', '-d', '--wait'], true);
        $this->assertTrue($process->isSuccessful(), substr($process->getErrorOutput(), -1500));
        $keyHash = hash_file('sha256', $this->environmentFile);
        $publicId = 'fd7310cd-261e-42b0-82f2-788cc92e02c1';
        $code = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); App\\Models\\Scan::create(["public_id"=>"'.$publicId.'","domain"=>"fixture.test","selectors"=>[],"status"=>"completed","deadline"=>now()]);';
        $this->assertTrue($this->compose(['exec', '-T', 'app', 'php', '-r', $code], true)->isSuccessful());
        $this->assertTrue($this->compose(['restart', 'app', 'worker', 'scheduler'], true)->isSuccessful());
        $process = $this->compose(['up', '-d', '--wait'], true);
        $this->assertTrue($process->isSuccessful());
        file_put_contents($this->overrideFile, str_replace("{$subnet}.20", "{$subnet}.21", $configuration));
        $process = $this->compose(['up', '-d', '--force-recreate', '--no-deps', '--wait', 'app'], true);
        $this->assertTrue($process->isSuccessful(), substr($process->getErrorOutput(), -1500));
        $port = trim($this->compose(['port', 'web', '80'], true)->getOutput());
        $content = false;
        $deadline = microtime(true) + 15;
        do {
            $content = @file_get_contents('http://'.$port.'/scans/'.$publicId.'/export', false, stream_context_create(['http' => ['timeout' => 2]]));
            if (is_string($content)) {
                break;
            }
            usleep(250000);
        } while (microtime(true) < $deadline);
        $this->assertIsString($content, $this->compose(['logs', '--no-color', '--tail=30', 'app', 'web'], true)->getOutput());
        $this->assertSame('fixture.test', json_decode($content, true, 512, JSON_THROW_ON_ERROR)['scan']['domain']);
        $this->assertTrue(hash_equals($keyHash, hash_file('sha256', $this->environmentFile)));
    }
}
