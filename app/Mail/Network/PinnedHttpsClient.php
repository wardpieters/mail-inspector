<?php

namespace App\Mail\Network;

use App\Mail\Dns\DnsLookup;
use App\Mail\Value\ScanBudget;

class PinnedHttpsClient
{
    public function __construct(private DnsLookup $lookup, private TargetGuard $guard) {}

    public function get(string $hostname, string $path, ScanBudget $budget): HttpsResult
    {
        if (! preg_match('/^[a-z0-9.-]+$/D', $hostname) || $path !== '/.well-known/mta-sts.txt') {
            return new HttpsResult(false, error: 'Unsupported policy URL.');
        }
        $addresses = [];
        $errors = [];
        foreach (['A', 'AAAA'] as $type) {
            $answer = $this->lookup->resolve($hostname, $type, $budget);
            if (! $answer->ok()) {
                $errors[] = $answer->status;
            }
            foreach ($answer->ofType() as $record) {
                try {
                    $ip = $this->guard->normalize($record['data']['address']);
                    $this->guard->assertPublic($ip);
                    $addresses[] = $ip;
                } catch (\Throwable $e) {
                    return new HttpsResult(false, error: 'Unsafe HTTPS destination blocked.');
                }
            }
        }
        if (! $addresses) {
            return new HttpsResult(false, error: 'No usable HTTPS address: '.implode(', ', $errors));
        }
        $last = new HttpsResult(false, error: 'Policy could not be fetched.');
        foreach (array_slice(array_unique($addresses), 0, 4) as $ip) {
            $budget->check();
            $body = '';
            $oversize = false;
            $curl = curl_init('https://'.$hostname.$path);
            curl_setopt_array($curl, [CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROXY => '', CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_RESOLVE => [$hostname.':443:'.(str_contains($ip, ':') ? '['.$ip.']' : $ip)], CURLOPT_CONNECTTIMEOUT_MS => (int) ($budget->timeout(5) * 1000), CURLOPT_TIMEOUT_MS => (int) ($budget->timeout(10) * 1000), CURLOPT_USERAGENT => 'MailInspector/1.0', CURLOPT_HTTPHEADER => ['Accept: text/plain'], CURLOPT_WRITEFUNCTION => function (mixed $handle, string $chunk) use (&$body, &$oversize): int {
                if (strlen($body) + strlen($chunk) > 65536) {
                    $oversize = true;

                    return 0;
                }$body .= $chunk;

                return strlen($chunk);
            }]);
            if (config('mail_scan.ca_file')) {
                curl_setopt($curl, CURLOPT_CAINFO, config('mail_scan.ca_file'));
            }
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $content = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
            $error = curl_error($curl);
            curl_close($curl);
            $last = new HttpsResult($ok !== false && $status === 200, $body, $status, $oversize ? 'Policy exceeds 64 KiB.' : ($ok === false ? substr($error, 0, 300) : ($status !== 200 ? 'Policy requires HTTP 200; redirects are rejected.' : null)), $ip, $content ?: null);
            if ($last->success || $oversize || ($status >= 300 && $status < 400)) {
                return $last;
            }
        }

        return $last;
    }
}
