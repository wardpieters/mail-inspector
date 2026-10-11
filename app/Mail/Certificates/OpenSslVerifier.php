<?php

namespace App\Mail\Certificates;

use App\Mail\Value\ScanBudget;
use Symfony\Component\Process\Process;

class OpenSslVerifier
{
    public function verifyPkix(array $chainPem, string $hostname, ScanBudget $budget): array
    {
        $args = ['-purpose', 'sslserver', '-verify_hostname', $hostname, '-attime', (string) now()->timestamp, '-auth_level', '0'];
        $ca = config('mail_scan.ca_file');
        if ($ca) {
            $args = array_merge($args, ['-CAfile', $ca]);
        }

        return $this->verify($chainPem, $args, $budget);
    }

    public function verifyDaneAnchor(array $chainPem, string $anchorPem, array $referenceNames, ScanBudget $budget): array
    {
        $path = tempnam(sys_get_temp_dir(), 'mail-anchor-');
        chmod($path, 0600);
        file_put_contents($path, $anchorPem);
        try {
            $last = ['valid' => false, 'error' => 'No reference identity.'];
            foreach (array_slice(array_unique($referenceNames), 0, 8) as $name) {
                $last = $this->verify($chainPem, ['-partial_chain', '-trusted', $path, '-no-CAfile', '-no-CApath', '-no-CAstore', '-purpose', 'sslserver', '-verify_hostname', $name, '-attime', (string) now()->timestamp], $budget);
                if ($last['valid']) {
                    return $last;
                }
            }

            return $last;
        } finally {
            unlink($path);
        }
    }

    private function verify(array $chainPem, array $args, ScanBudget $budget): array
    {
        if (! $chainPem) {
            return ['valid' => false, 'error' => 'No certificate chain.', 'available' => true];
        }
        $dir = sys_get_temp_dir().'/mail-verify-'.bin2hex(random_bytes(12));
        mkdir($dir, 0700);
        $leaf = $dir.'/leaf.pem';
        $chain = $dir.'/chain.pem';
        try {
            file_put_contents($leaf, $chainPem[0]);
            file_put_contents($chain, implode("\n", array_slice($chainPem, 1, 15)));
            $command = ['openssl', 'verify', ...$args];
            if (count($chainPem) > 1) {
                $command = [...$command, '-untrusted', $chain];
            }$command[] = $leaf;
            $process = new Process($command, null, null, null, $budget->timeout(5));
            $process->run();
            $error = substr($process->getErrorOutput().$process->getOutput(), 0, 1200);
            $error = str_replace([$leaf, $chain, $dir], ['leaf.pem', 'chain.pem', '[temporary]'], $error);

            return ['valid' => $process->isSuccessful(), 'error' => $process->isSuccessful() ? null : $error, 'available' => $process->getExitCode() !== 127];
        } catch (\Throwable $e) {
            return ['valid' => false, 'error' => 'Certificate verifier unavailable or timed out.', 'available' => false];
        } finally {
            foreach ([$leaf, $chain] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }rmdir($dir);
        }
    }

    public static function der(string $pem): string
    {
        return base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem), true) ?: '';
    }

    public static function spki(string $pem): string
    {
        $key = openssl_pkey_get_public($pem);
        $details = $key ? openssl_pkey_get_details($key) : false;

        return $details ? self::der($details['key']) : '';
    }
}
