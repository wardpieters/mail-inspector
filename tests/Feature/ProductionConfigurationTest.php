<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProductionConfigurationTest extends TestCase
{
    public function test_compose_binds_only_web_and_orders_migrations_before_consumers(): void
    {
        $env = tempnam(sys_get_temp_dir(), 'mail-env-');
        unlink($env);
        try {
            (new Process(['php', base_path('scripts/setup-env.php'), $env]))->mustRun();
            $process = new Process(['docker', 'compose', '--env-file', $env, 'config', '--format', 'json'], base_path());
            $process->mustRun();
            $config = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('127.0.0.1', $config['services']['web']['ports'][0]['host_ip']);
            foreach (['postgres', 'redis', 'unbound', 'app', 'worker', 'scheduler'] as $service) {
                $this->assertArrayNotHasKey('ports', $config['services'][$service]);
            }
            foreach (['app', 'worker', 'scheduler'] as $service) {
                $this->assertSame('service_completed_successfully', $config['services'][$service]['depends_on']['migrate']['condition']);
                $this->assertSame('false', $config['services'][$service]['environment']['APP_DEBUG']);
            }
            $this->assertSame('stderr', $config['services']['app']['environment']['LOG_CHANNEL']);
            $this->assertSame('300', $config['services']['worker']['environment']['REDIS_QUEUE_RETRY_AFTER']);
        } finally {
            if (is_file($env)) {
                unlink($env);
            }
        }
    }

    public function test_setup_preserves_existing_secrets_and_does_not_print_them(): void
    {
        $env = tempnam(sys_get_temp_dir(), 'mail-env-');
        file_put_contents($env, "APP_KEY=existing-key\nDB_PASSWORD=existing-database-secret\nREDIS_PASSWORD=existing-redis-secret\n");
        try {
            $process = new Process(['php', base_path('scripts/setup-env.php'), $env]);
            $process->mustRun();
            $this->assertSame("APP_KEY=existing-key\nDB_PASSWORD=existing-database-secret\nREDIS_PASSWORD=existing-redis-secret\n", file_get_contents($env));
            $this->assertStringNotContainsString('existing-key', $process->getOutput());
            $this->assertSame(0600, fileperms($env) & 0777);
        } finally {
            unlink($env);
        }
    }
}
