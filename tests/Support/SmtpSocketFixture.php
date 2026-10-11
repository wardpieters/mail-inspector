<?php

namespace Tests\Support;

class SmtpSocketFixture
{
    private mixed $process;

    private array $pipes = [];

    public readonly string $address;

    public readonly string $log;

    public function __construct(int $family = 4, string $mode = 'tls')
    {
        $this->log = tempnam(sys_get_temp_dir(), 'mail-smtp-log-');
        $bind = $family === 6 ? 'tcp://[::1]:0' : 'tcp://127.0.0.1:0';
        $certificate = CertificateFixtures::serverCertificate();
        $this->process = proc_open([PHP_BINARY, __DIR__.'/../Fixtures/smtp/server.php', $bind, $mode, $certificate, $this->log], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $this->pipes);
        if (! is_resource($this->process)) {
            throw new \RuntimeException('Could not start SMTP fixture.');
        }stream_set_timeout($this->pipes[1], 10);
        $address = fgets($this->pipes[1]);
        if (! $address) {
            $error = stream_get_contents($this->pipes[2]);
            $this->close();
            throw new \RuntimeException('SMTP fixture unavailable: '.$error);
        }$this->address = trim($address);
    }

    public function observations(): array
    {
        return array_values(array_filter(array_map(fn ($line) => json_decode($line, true), explode("\n", trim(file_get_contents($this->log))))));
    }

    public function close(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }proc_close($this->process);
            $this->process = null;
        }if (is_file($this->log)) {
            unlink($this->log);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
