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

    public function test_reports_and_application_key_survive_container_restarts(): void
    {
        $process = $this->compose(['up', '-d', '--wait']);
        $this->assertTrue($process->isSuccessful(), substr($process->getErrorOutput(), -1500));
        $keyHash = hash_file('sha256', $this->environmentFile);
        $publicId = 'fd7310cd-261e-42b0-82f2-788cc92e02c1';
        $code = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); App\\Models\\Scan::create(["public_id"=>"'.$publicId.'","domain"=>"fixture.test","selectors"=>[],"status"=>"completed","deadline"=>now()]);';
        $this->assertTrue($this->compose(['exec', '-T', 'app', 'php', '-r', $code])->isSuccessful());
        $this->assertTrue($this->compose(['restart', 'app', 'worker', 'scheduler'])->isSuccessful());
        $process = $this->compose(['up', '-d', '--wait']);
        $this->assertTrue($process->isSuccessful());
        $port = trim($this->compose(['port', 'web', '80'])->getOutput());
        $content = file_get_contents('http://'.$port.'/scans/'.$publicId.'/export');
        $this->assertSame('fixture.test', json_decode($content, true, 512, JSON_THROW_ON_ERROR)['scan']['domain']);
        $this->assertTrue(hash_equals($keyHash, hash_file('sha256', $this->environmentFile)));
    }
}
